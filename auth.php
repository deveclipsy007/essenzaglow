<?php
// auth.php - Sistema de Autenticação
session_start();

/**
 * Verifica se o usuário está autenticado
 * Se não estiver, redireciona para login.php
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Retorna os dados do usuário logado
 */
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['user_role'] ?? null
    ];
}

/**
 * Verifica se está autenticado (sem redirecionar)
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

/**
 * Realiza o login do usuário
 */
function login($pdo, $username, $password) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }
    
    return false;
}

/**
 * Realiza o logout
 */
function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
