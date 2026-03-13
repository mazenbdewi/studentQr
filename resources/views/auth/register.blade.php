@extends('layouts.auth')

@section('title', __('auth.register_title'))
@error('name')
<p class="text-red-600 text-sm mt-1">{{ $message }}</p>
@enderror

@error('email')
<p class="text-red-600 text-sm mt-1">{{ $message }}</p>
@enderror

@error('faculty_id')
<p class="text-red-600 text-sm mt-1">{{ $message }}</p>
@enderror

@error('department_id')
<p class="text-red-600 text-sm mt-1">{{ $message }}</p>
@enderror

@error('year')
<p class="text-red-600 text-sm mt-1">{{ $message }}</p>
@enderror

@error('password')
<p class="text-red-600 text-sm mt-1">{{ $message }}</p>
@enderror
@section('content')

    <div class="text-center mb-6">
        <div class="w-33 h-33 mx-auto rounded-full bg-gray-100 flex items-center justify-center overflow-hidden  shadow-sm">

            <img id="avatarPreview"
                 src="{{ asset('images/logo.png') }}"
                 class="w-22 h-22 object-contain">

        </div>

        <p class="text-sm text-gray-500 mt-2">
            {{ __('auth.avatar') }}
        </p>
    </div>

    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
        @csrf

        <div class="grid md:grid-cols-2 gap-8 w-full">          
            <div>
                <label class="block mb-2">{{ __('auth.student_number') }}</label>
                <input type="text" name="student_number"
                       class="w-full border p-3 uppercase rounded-lg"
                       required>
                @error('student_number')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

           
            <div>
                <label class="block mb-2">{{ __('auth.name') }}</label>
                <input type="text" name="name"
                       class="w-full border p-3 rounded-lg"
                       required>
            </div>

             <div>
                <label class="block mb-2">{{ __('auth.email') }}</label>
                <input type="email" name="email"
                       class="w-full border p-3 rounded-lg"
                       required>
            </div>

          
            <div>
                <label class="block mb-2">{{ __('auth.faculty') }}</label>

                <select name="faculty_id"
                        class="w-full border p-3 rounded-lg"
                        required>
                    <option value="">{{ __('auth.choose_faculty') }}</option>

                    @foreach($faculties as $faculty)
                        <option value="{{ $faculty->id }}">
                            {{ $faculty->name }}
                        </option>
                    @endforeach
                </select>
            </div>

          
            <div>
                <label class="block mb-2">{{ __('auth.department') }}</label>

                <select name="department_id"
                        class="w-full border p-3 rounded-lg"
                        required>
                    <option value="">{{ __('auth.choose_department') }}</option>
                </select>
            </div>

          
            <div>
                <label class="block mb-2">{{ __('auth.year') }}</label>

                <select name="year"
                        class="w-full border p-3 rounded-lg"
                        required>
                    <option value="1">{{ __('auth.year1') }}</option>
                    <option value="2">{{ __('auth.year2') }}</option>
                    <option value="3">{{ __('auth.year3') }}</option>
                    <option value="4">{{ __('auth.year4') }}</option>
                </select>
            </div>

           
            <div>
                <label class="block mb-2">{{ __('auth.avatar') }}</label>
                <input type="file" name="avatar"
                       class="w-full border p-3 rounded-lg">
            </div>

           
            <div>
                <label class="block mb-2">{{ __('auth.password') }}</label>
                <input type="password" name="password"
                       class="w-full border p-3 rounded-lg"
                       required>
            </div>
 
            <div>
                <label class="block mb-2">{{ __('auth.confirm_password') }}</label>
                <input type="password" name="password_confirmation"
                       class="w-full border p-3 rounded-lg"
                       required>
            </div>

        </div>

        <button class="w-full mt-8 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-xl transition">
            {{ __('auth.register') }}
        </button>

    </form>
    <script>
        document.querySelector('[name="faculty_id"]').addEventListener('change', function(){

            let facultyId = this.value;
            let departmentSelect = document.querySelector('[name="department_id"]');

            if(!facultyId){
                departmentSelect.innerHTML = '<option value="">{{ __("auth.choose_department") }}</option>';
                return;
            }

            departmentSelect.innerHTML = '<option>Loading...</option>';

            fetch('/departments/' + facultyId)
                .then(res => res.json())
                .then(data => {

                    departmentSelect.innerHTML =
                        '<option value="">{{ __("auth.choose_department") }}</option>';

                    data.forEach(dep=>{
                        departmentSelect.innerHTML +=
                            `<option value="${dep.id}">${dep.name}</option>`;
                    });

                });

        });
    </script>

@endsection
