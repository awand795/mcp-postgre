<?php

test('the application redirects guest to login', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
});
