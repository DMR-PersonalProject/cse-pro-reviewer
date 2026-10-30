<?php

it('boots the app', function () {
    $this->get('/')->assertOk();
});

it('serves the admin login page', function () {
    $this->get('/admin/login')->assertOk();
});
