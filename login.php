<?php
session_start();
require_once 'conexion.php';

// Si el usuario ya está autenticado, lo redirige al panel principal
if (isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo']);
    $clave = $_POST['clave'];

    if (!empty($correo) && !empty($clave)) {
        $stmt = $pdo->prepare("SELECT * FROM usuario WHERE correo = :c");
        $stmt->execute(['c' => $correo]);
        $user = $stmt->fetch();

        if ($user && password_verify($clave, $user['clave'])) {
            $_SESSION['id_usuario'] = $user['id_usuario'];
            $_SESSION['nombre_usuario'] = $user['nombre'];
            $_SESSION['rol'] = $user['rol'] ?? 'vendedor';

            header("Location: index.php");
            exit;
        } else {
            $error = "Correo o contraseña incorrectos.";
        }
    } else {
        $error = "Por favor, completa todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoStock - Iniciar Sesión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-header bg-dark text-white text-center py-3">
                        <h4 class="mb-0 fw-bold text-primary">TecnoStock</h4>
                        <small>Iniciar Sesión</small>
                    </div>
                    <div class="card-body p-4">

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger py-2 text-center" role="alert">
                                <?= $error ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="login.php">
                            <div class="mb-3">
                                <label class="form-label">Correo Electrónico</label>
                                <input type="email" name="correo" class="form-control" required placeholder="correo@ejemplo.com">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input type="password" name="clave" class="form-control" required placeholder="••••••••">
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Ingresar</button>
                        </form>

                        <hr class="my-4">

                        <!-- ENLACE A LA PÁGINA DE REGISTRO -->
                        <div class="text-center">
                            <p class="text-muted small mb-2">¿No tienes una cuenta aún?</p>
                            <a href="registro.php" class="btn btn-outline-success btn-sm w-100">Registrarse aquí</a>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>