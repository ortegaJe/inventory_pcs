<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    //Relacion uno a muchos
    public function profiles()
    {
        return $this->hasMany(Profile::class);
    }

    //Relacion uno a muchos
    public function campus()
    {
        return $this->hasMany(CampuUser::class);
    }

    public function scopeSearchUser($query, $data)
    {
        if (trim($data) != "") {
            return $query->where((DB::raw("CONCAT(name,' ',last_name)")), 'LIKE', "%$data%")
                ->orWhere('last_name', 'LIKE', "%$data%")
                ->orWhere('cc', 'LIKE', "%$data%");
        }
    }

    public function scopeUserWithCampu($query)
    {
        return $query->join('campu_users', 'users.id', '=', 'campu_users.user_id')
            ->join('campus', 'campu_users.campu_id', '=', 'campus.id')
            ->where('campu_users.is_principal', true)
            ->orderBy('users.name', 'asc')
            ->paginate(8);
        //->get();
    }
}
