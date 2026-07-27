<?php

test('the application redirects unauthenticated visitors to username login', function () {
    $this->get('/')
        ->assertRedirect(route('login'));
});
