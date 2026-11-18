<?php
session_start();
require_once 'conexion.php';

if (isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit;
}

$mensaje = "";
$tipo_alerta = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $clave = $_POST['clave'];
    $clave_confirmar = $_POST['clave_confirmar'];
    $rol = 'vendedor'; // ROL POR DEFECTO: Siempre será vendedor para nuevos registros

    if (!empty($nombre) && !empty($correo) && !empty($clave)) {
        if ($clave === $clave_confirmar) {
            try {
                $stmt_check = $pdo->prepare("SELECT id_usuario FROM usuario WHERE correo = :correo");
                $stmt_check->execute(['correo' => $correo]);
                
                if ($stmt_check->rowCount() > 0) {
                    $mensaje = "El correo electrónico ya está registrado.";
                    $tipo_alerta = "danger";
                } else {
                    $clave_hash = password_hash($clave, PASSWORD_BCRYPT);

                    $stmt = $pdo->prepare("INSERT INTO usuario (nombre, correo, clave, rol) VALUES (:nombre, :correo, :clave, :rol)");
                    $stmt->execute([
                        'nombre' => $nombre,
                        'correo' => $correo,
                        'clave' => $clave_hash,
                        'rol' => $rol
                    ]);

                    $mensaje = "¡Cuenta creada exitosamente! Ya puedes iniciar sesión.";
                    $tipo_alerta = "success";
                }
            } catch (PDOException $e) {
                $mensaje = "Error al registrar usuario: " . $e->getMessage();
                $tipo_alerta = "danger";
            }
        } else {
            $mensaje = "Las contraseñas no coinciden.";
            $tipo_alerta = "warning";
        }
    } else {
        $mensaje = "Por favor completa todos los campos obligatorios.";
        $tipo_alerta = "warning";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoStock - Crear Cuenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h4 class="mb-0 fw-bold">TecnoStock</h4>
                        <small>Crear una nueva cuenta de Vendedor</small>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($mensaje)): ?>
                            <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
                                <?= $mensaje ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="registro.php">
                            <div class="mb-3">
                                <label class="form-label">Nombre Completo</label>
                                <input type="text" name="nombre" class="form-control" placeholder="Ej: Juan Pérez" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Correo Electrónico</label>
                                <input type="email" name="correo" class="form-control" placeholder="ejemplo@correo.com" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input type="password" name="clave" class="form-control" placeholder="••••••••" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirmar Contraseña</label>
                                <input type="password" name="clave_confirmar" class="form-control" placeholder="••••••••" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 py-2">Registrarse como Vendedor</button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="login.php" class="text-decoration-none">¿Ya tienes cuenta? Inicia sesión aquí</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>