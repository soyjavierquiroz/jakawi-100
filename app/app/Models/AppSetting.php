<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AppSetting extends Model { protected $guarded = []; protected function casts(): array { return ['value' => 'array']; } }
