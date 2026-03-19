<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SynchronizerServer extends Model
{
    protected $fillable = ['name', 'url', 'api_token', 'ingest_secret', 'install_dir'];
}
