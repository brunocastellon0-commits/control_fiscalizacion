<?php

test('the login view returns a successful response', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});
