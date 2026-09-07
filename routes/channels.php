<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return $user->id == $userId;
});
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});