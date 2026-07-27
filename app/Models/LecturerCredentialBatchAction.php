<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LecturerCredentialBatchAction extends Model
{
    protected $fillable = ['lecturer_credential_batch_id', 'action', 'performed_by', 'request_ip', 'safe_metadata', 'performed_at'];

    protected $casts = ['safe_metadata' => 'array', 'performed_at' => 'datetime'];
}
