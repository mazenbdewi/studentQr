import http from 'k6/http';
import { check, fail } from 'k6';
import { SharedArray } from 'k6/data';
import exec from 'k6/execution';

const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const SESSION_ID = __ENV.SESSION_ID;
const QR_TOKEN = __ENV.QR_TOKEN;
const OTP = __ENV.OTP;
const STUDENTS_FILE = __ENV.STUDENTS_FILE || 'students.csv';
const VUS = Number(__ENV.VUS || 100);
const MAX_DURATION = __ENV.MAX_DURATION || '5m';

if (!SESSION_ID || !QR_TOKEN || !OTP) {
  throw new Error('Set SESSION_ID, QR_TOKEN, and OTP before running this script.');
}

const students = new SharedArray('student_numbers', () => {
  return open(STUDENTS_FILE)
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line !== '' && !line.startsWith('#'))
    .map((line) => line.split(',')[0].trim())
    .filter(Boolean);
});

export const options = {
  scenarios: {
    attendance_once_per_student: {
      executor: 'per-vu-iterations',
      vus: VUS,
      iterations: 1,
      maxDuration: MAX_DURATION,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    'http_req_duration{name:verify_qr}': ['p(95)<1000'],
    'http_req_duration{name:store_attendance}': ['p(95)<1500'],
  },
};

function parseJson(response) {
  try {
    return response.json();
  } catch (_) {
    return null;
  }
}

export default function () {
  const studentIndex = exec.scenario.iterationInTest;

  if (studentIndex >= students.length) {
    fail(`Not enough student numbers in ${STUDENTS_FILE}. Need at least ${VUS}.`);
  }

  const studentNumber = students[studentIndex];

  const verifyResponse = http.get(`${BASE_URL}/student/attendance/verify/${QR_TOKEN}`, {
    tags: { name: 'verify_qr' },
  });

  check(verifyResponse, {
    'QR verification page loaded': (response) => response.status === 200,
  });

  if (verifyResponse.status !== 200) {
    fail(`QR verification failed with HTTP ${verifyResponse.status}.`);
  }

  const page = verifyResponse.html();
  const csrfToken = page.find('input[name="_token"]').attr('value');
  const submissionToken = page.find('input[name="submission_token"]').attr('value');

  if (!submissionToken) {
    fail('Could not find submission token on the attendance page.');
  }

  const payload = {
    student_number: studentNumber,
    otp: OTP,
    submission_token: submissionToken,
  };

  if (csrfToken) {
    payload._token = csrfToken;
  }

  const storeResponse = http.post(
    `${BASE_URL}/student/attendance/store-sync/${SESSION_ID}`,
    payload,
    {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-Device-Fingerprint': `k6-${studentNumber}`,
      },
      tags: { name: 'store_attendance' },
    },
  );

  const data = parseJson(storeResponse);

  const stored = check(storeResponse, {
    'attendance request succeeded': (response) => response.status === 200,
    'attendance response is successful': () => data !== null && data.success === true,
  });

  if (!stored) {
    console.error(
      `Attendance failed for student ${studentNumber}: HTTP ${storeResponse.status} ${storeResponse.body}`,
    );
  }
}
