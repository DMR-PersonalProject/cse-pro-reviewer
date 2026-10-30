<?php

it('boots the app', function () {
    $this->get('/start')->assertOk();
});

it('serves the admin login page', function () {
    $this->get('/admin/login')->assertOk();
});
