<?php

test('the application redirects to the login selector', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
