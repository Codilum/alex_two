<?php
$error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'invalid') {
        $error = 'Пароль не найден или введен неверно. Попробуйте снова.';
    } elseif ($_GET['error'] === 'empty') {
        $error = 'Введите имя пользователя и пароль.';
    }
}
?>
<div class="login-card">
    <div class="login-header">
        <div class="login-title">Добро пожаловать</div>
        <div class="login-subtitle">Введите имя пользователя и пароль для входа.</div>
    </div>
    <?php if ($error !== '') { ?>
        <div class="login-error"><?php echo htmlspecialchars($error); ?></div>
    <?php } ?>
    <form method="POST" action="auth.php" class="login-form">
        <label class="login-label">
            Имя пользователя
            <input name="login" placeholder="Например: test" type="text" autocomplete="username" required>
        </label>
        <label class="login-label">
            Пароль
            <input name="password" placeholder="Введите пароль" type="password" autocomplete="current-password" required>
        </label>
        <input name="submit" type="submit" value="Войти" class="login-submit">
    </form>
</div>
