<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');
    $response->assertOk();
});

test('new users can register and are redirected to dashboard', function () {
    $response = $this->post('/register', [
        'name' => 'Test Host',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');
});

test('registration creates a user record', function () {
    $this->post('/register', [
        'name' => 'New Host',
        'email' => 'newhost@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertDatabaseHas('users', ['email' => 'newhost@example.com', 'name' => 'New Host']);
});

test('registration requires a name', function () {
    $response = $this->post('/register', [
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('name');
    $this->assertGuest();
});

test('registration requires a valid email', function () {
    $response = $this->post('/register', [
        'name' => 'Test',
        'email' => 'not-an-email',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('registration accepts a 4-character password', function () {
    $response = $this->post('/register', [
        'name' => 'Test Host',
        'email' => 'test@example.com',
        'password' => 'abcd',
        'password_confirmation' => 'abcd',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

test('registration rejects a 3-character password', function () {
    $response = $this->post('/register', [
        'name' => 'Test Host',
        'email' => 'test@example.com',
        'password' => 'abc',
        'password_confirmation' => 'abc',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

test('registration requires a confirmed password', function () {
    $response = $this->post('/register', [
        'name' => 'Test',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});
