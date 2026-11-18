<?php
session_start();
require_once 'conexion.php';

// Validar inicio de sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$rol_usuario = $_SESSION['rol'] ?? 'vendedor'; 
$id_usuario_sesion = $_SESSION['id_usuario'] ?? 1;
$mensaje = "";
$tipo_alerta = "";

// Determinar el estado a mostrar (1 = Activos por defecto, 0 = Desactivados)
$ver_estado = isset($_GET['estado_ver']) && $_GET['estado_ver'] == '0' ? 0 : 1;

// 1. PROCESAR CREACIÓN DE CATEGORÍA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_categoria') {
    if ($rol_usuario !== 'admin') {
        $mensaje = "Acceso denegado: Solo los administradores pueden agregar categorías.";
        $tipo_alerta = "danger";
    } else {
        $nombre_cat = trim($_POST['nombre_categoria'] ?? '');
        if (!empty($nombre_cat)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categoria (nombre) VALUES (:nombre)");
                $stmt->execute(['nombre' => $nombre_cat]);
                $mensaje = "¡Categoría '$nombre_cat' agregada exitosamente!";
                $tipo_alerta = "success";
            } catch (PDOException $e) {
                $mensaje = "Error: La categoría ya existe o hubo un problema en la base de datos.";
                $tipo_alerta = "danger";
            }
        } else {
            $mensaje = "Por favor ingresa un nombre válido para la categoría.";
            $tipo_alerta = "warning";
        }
    }
}

// 2. PROCESAR GUARDADO DE NUEVO PRODUCTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
    if ($rol_usuario !== 'admin') {
        $mensaje = "Acceso denegado: Solo los administradores pueden registrar productos.";
        $tipo_alerta = "danger";
    } else {
        $codigo = trim($_POST['codigo']);
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $id_categoria = $_POST['id_categoria'];
        $precio = $_POST['precio'];
        $stock_actual = $_POST['stock_actual'];
        $stock_minimo = $_POST['stock_minimo'];

        if (!empty($codigo) && !empty($nombre) && !empty($id_categoria)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO producto (codigo, nombre, descripcion, id_categoria, precio, stock_actual, stock_minimo, estado) 
                                       VALUES (:codigo, :nombre, :descripcion, :id_cat, :precio, :stock, :stock_min, 1)");
                $stmt->execute([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'id_cat' => $id_categoria,
                    'precio' => $precio,
                    'stock' => $stock_actual,
                    'stock_min' => $stock_minimo
                ]);
                $mensaje = "¡Producto registrado exitosamente!";
                $tipo_alerta = "success";
            } catch (PDOException $e) {
                $mensaje = "Error: El código del producto ya existe o hubo un problema en la base de datos.";
                $tipo_alerta = "danger";
            }
        } else {
            $mensaje = "Por favor completa todos los campos obligatorios.";
            $tipo_alerta = "warning";
        }
    }
}

// 3. PROCESAR EDICIÓN DE PRODUCTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    if ($rol_usuario !== 'admin') {
        $mensaje = "Acceso denegado: Solo los administradores pueden editar productos.";
        $tipo_alerta = "danger";
    } else {
        $id_producto = $_POST['id_producto'];
        $nombre = trim($_POST['nombre']);
        $descripcion = trim($_POST['descripcion'] ?? '');
        $id_categoria = $_POST['id_categoria'];
        $precio = $_POST['precio'];
        $stock_actual = $_POST['stock_actual'];
        $stock_minimo = $_POST['stock_minimo'];

        if (!empty($nombre) && !empty($id_categoria)) {
            try {
                $stmt = $pdo->prepare("UPDATE producto 
                                       SET nombre = :nombre, 
                                           descripcion = :descripcion, 
                                           id_categoria = :id_cat, 
                                           precio = :precio, 
                                           stock_actual = :stock, 
                                           stock_minimo = :stock_min 
                                       WHERE id_producto = :id");
                $stmt->execute([
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'id_cat' => $id_categoria,
                    'precio' => $precio,
                    'stock' => $stock_actual,
                    'stock_min' => $stock_minimo,
                    'id' => $id_producto
                ]);
                $mensaje = "¡Producto actualizado correctamente!";
                $tipo_alerta = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar el producto: " . $e->getMessage();
                $tipo_alerta = "danger";
            }
        } else {
            $mensaje = "Por favor completa todos los campos obligatorios.";
            $tipo_alerta = "warning";
        }
    }
}

// 4. PROCESAR REGISTRO DE MOVIMIENTO DE STOCK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'movimiento') {
    $id_producto = $_POST['id_producto'];
    $tipo_movimiento = $_POST['tipo_movimiento'];
    $cantidad = (int)$_POST['cantidad'];
    $motivo = trim($_POST['motivo'] ?? '');

    if ($cantidad > 0 && !empty($tipo_movimiento)) {
        $stmt_check = $pdo->prepare("SELECT stock_actual FROM producto WHERE id_producto = :id");
        $stmt_check->execute(['id' => $id_producto]);
        $prod_actual = $stmt_check->fetch();

        if ($prod_actual) {
            $stock_previo = (int)$prod_actual['stock_actual'];

            if ($tipo_movimiento === 'Salida' && $cantidad > $stock_previo) {
                $mensaje = "Error: No hay suficiente stock disponible para realizar la salida. Stock actual: $stock_previo";
                $tipo_alerta = "danger";
            } else {
                $nuevo_stock = ($tipo_movimiento === 'Entrada') ? ($stock_previo + $cantidad) : ($stock_previo - $cantidad);

                try {
                    $pdo->beginTransaction();

                    $stmt_upd = $pdo->prepare("UPDATE producto SET stock_actual = :n_stock WHERE id_producto = :id");
                    $stmt_upd->execute(['n_stock' => $nuevo_stock, 'id' => $id_producto]);

                    // Insert con soporte para fecha si la columna existe
                    try {
                        $stmt_mov = $pdo->prepare("INSERT INTO movimiento (id_producto, id_usuario, tipo, cantidad, fecha, observacion) 
                                                   VALUES (:id_prod, :id_usu, :tipo, :cant, NOW(), :obs)");
                        $stmt_mov->execute([
                            'id_prod' => $id_producto,
                            'id_usu' => $id_usuario_sesion,
                            'tipo' => $tipo_movimiento,
                            'cant' => $cantidad,
                            'obs' => $motivo
                        ]);
                    } catch (PDOException $e) {
                        // Fallback por si no han ejecutado la instrucción SQL de fecha
                        $stmt_mov = $pdo->prepare("INSERT INTO movimiento (id_producto, id_usuario, tipo, cantidad, observacion) 
                                                   VALUES (:id_prod, :id_usu, :tipo, :cant, :obs)");
                        $stmt_mov->execute([
                            'id_prod' => $id_producto,
                            'id_usu' => $id_usuario_sesion,
                            'tipo' => $tipo_movimiento,
                            'cant' => $cantidad,
                            'obs' => $motivo
                        ]);
                    }

                    $pdo->commit();
                    $mensaje = "¡Movimiento de $tipo_movimiento registrado correctamente! Nuevo stock: $nuevo_stock";
                    $tipo_alerta = "success";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $mensaje = "Error al registrar movimiento: " . $e->getMessage();
                    $tipo_alerta = "danger";
                }
            }
        }
    } else {
        $mensaje = "Por favor ingresa una cantidad válida mayor a cero.";
        $tipo_alerta = "warning";
    }
}

// 5. PROCESAR DESACTIVAR O REACTIVAR PRODUCTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
    if ($rol_usuario !== 'admin') {
        $mensaje = "Acceso denegado: Acción reservada para administradores.";
        $tipo_alerta = "danger";
    } else {
        $id_producto = $_POST['id_producto'];
        $nuevo_estado = $_POST['nuevo_estado'];
        
        try {
            $stmt = $pdo->prepare("UPDATE producto SET estado = :est WHERE id_producto = :id");
            $stmt->execute(['est' => $nuevo_estado, 'id' => $id_producto]);

            $mensaje = $nuevo_estado == 1 ? "¡Producto reactivado exitosamente!" : "¡Producto desactivado del inventario activo!";
            $tipo_alerta = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al cambiar el estado del producto: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

// GENERAR CÓDIGO AUTOMÁTICO
$stmt_max = $pdo->query("SELECT MAX(id_producto) as max_id FROM producto");
$max_id = $stmt_max->fetch()['max_id'] ?? 0;
$codigo_siguiente = 'PROD-' . str_pad($max_id + 1, 3, '0', STR_PAD_LEFT);

// BÚSQUEDA Y LISTADO DE PRODUCTOS
$busqueda = trim($_GET['busqueda'] ?? '');

$sql = "SELECT p.*, c.nombre AS categoria_nombre 
        FROM producto p 
        INNER JOIN categoria c ON p.id_categoria = c.id_categoria 
        WHERE p.estado = :estado";

if (!empty($busqueda)) {
    $sql .= " AND (p.nombre LIKE :b1 OR p.codigo LIKE :b2 OR c.nombre LIKE :b3)";
}

$sql .= " ORDER BY p.id_producto DESC";

$stmt = $pdo->prepare($sql);
$params = ['estado' => $ver_estado];

if (!empty($busqueda)) {
    $termino = "%" . $busqueda . "%";
    $params['b1'] = $termino;
    $params['b2'] = $termino;
    $params['b3'] = $termino;
}

$stmt->execute($params);
$productos = $stmt->fetchAll();

// OBTENER CATEGORÍAS
$stmt_cat = $pdo->query("SELECT * FROM categoria ORDER BY nombre ASC");
$categorias = $stmt_cat->fetchAll();

// OBTENER HISTORIAL DE MOVIMIENTOS
try {
    $stmt_hist = $pdo->query("SELECT m.*, p.nombre AS producto_nombre, p.codigo AS producto_codigo, u.nombre AS usuario_nombre 
                              FROM movimiento m 
                              INNER JOIN producto p ON m.id_producto = p.id_producto 
                              LEFT JOIN usuario u ON m.id_usuario = u.id_usuario 
                              ORDER BY m.id_movimiento DESC LIMIT 50");
    $movimientos_historial = $stmt_hist->fetchAll();
} catch (PDOException $e) {
    $movimientos_historial = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoStock - Panel de Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php">TecnoStock</a>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-2 text-white">
                    Hola, <strong><?= htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario') ?></strong>
                </span>
                <span class="badge bg-<?= $rol_usuario === 'admin' ? 'danger' : 'info' ?> me-3">
                    <?= strtoupper($rol_usuario) ?>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <div class="container">
        
        <!-- Notificaciones -->
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
                <?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Acciones: Botón Nuevo + Categoría + Historial + Filtro + Buscador -->
        <div class="row mb-3 align-items-center">
            <div class="col-md-7 mb-2 mb-md-0 d-flex align-items-center gap-2 flex-wrap">
                <?php if ($rol_usuario === 'admin'): ?>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalCrearProducto">
                        <i class="bi bi-plus-circle me-1"></i> Nuevo Producto
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalCrearCategoria">
                        <i class="bi bi-tags-fill me-1"></i> Nueva Categoría
                    </button>
                <?php else: ?>
                    <span class="badge bg-secondary p-2"><i class="bi bi-eye-fill me-1"></i> VENDEDOR</span>
                <?php endif; ?>

                <!-- Botón Historial de Movimientos -->
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalHistorial">
                    <i class="bi bi-clock-history me-1"></i> Ver Historial
                </button>

                <div class="btn-group">
                    <a href="index.php?estado_ver=1" class="btn btn-<?= $ver_estado == 1 ? 'dark' : 'outline-dark' ?> btn-sm">Activos</a>
                    <a href="index.php?estado_ver=0" class="btn btn-<?= $ver_estado == 0 ? 'secondary' : 'outline-secondary' ?> btn-sm">Desactivados</a>
                </div>
            </div>
            <div class="col-md-5">
                <form method="GET" action="index.php" class="d-flex ms-auto">
                    <input type="hidden" name="estado_ver" value="<?= $ver_estado ?>">
                    <input type="text" name="busqueda" class="form-control me-2" placeholder="Buscar..." value="<?= htmlspecialchars($busqueda) ?>">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="index.php?estado_ver=<?= $ver_estado ?>" class="btn btn-outline-secondary ms-2">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Tabla de Productos -->
        <div class="card shadow-sm mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Categoría</th>
                                <th>Precio</th>
                                <th>Stock Actual</th>
                                <th>Stock Mínimo</th>
                                <th>Estado Stock</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($productos) > 0): ?>
                                <?php foreach ($productos as $prod): ?>
                                    <?php $bajo_stock = ($prod['stock_actual'] < $prod['stock_minimo']) && $ver_estado == 1; ?>
                                    <tr class="<?= $bajo_stock ? 'table-danger' : '' ?>">
                                        <td><strong><?= htmlspecialchars($prod['codigo']) ?></strong></td>
                                        <td><?= htmlspecialchars($prod['nombre']) ?></td>
                                        <td><small class="text-muted"><?= htmlspecialchars($prod['descripcion'] ?? 'Sin descripción') ?></small></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($prod['categoria_nombre']) ?></span></td>
                                        <td>$<?= number_format($prod['precio'], 0, ',', '.') ?></td>
                                        <td><strong><?= $prod['stock_actual'] ?></strong></td>
                                        <td><?= $prod['stock_minimo'] ?></td>
                                        <td>
                                            <?php if ($ver_estado == 0): ?>
                                                <span class="badge bg-secondary">Inactivo</span>
                                            <?php elseif ($bajo_stock): ?>
                                                <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill"></i> ¡Bajo Stock!</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Normal</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($ver_estado == 1): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info me-1" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#modalMovimiento"
                                                        data-id="<?= $prod['id_producto'] ?>"
                                                        data-nombre="<?= htmlspecialchars($prod['nombre']) ?>"
                                                        data-stock="<?= $prod['stock_actual'] ?>"
                                                        title="Registrar Entrada/Salida de Stock">
                                                    <i class="bi bi-arrow-down-up"></i>
                                                </button>

                                                <?php if ($rol_usuario === 'admin'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-warning me-1" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#modalEditarProducto"
                                                            data-id="<?= $prod['id_producto'] ?>"
                                                            data-codigo="<?= htmlspecialchars($prod['codigo']) ?>"
                                                            data-nombre="<?= htmlspecialchars($prod['nombre']) ?>"
                                                            data-descripcion="<?= htmlspecialchars($prod['descripcion'] ?? '') ?>"
                                                            data-categoria="<?= $prod['id_categoria'] ?>"
                                                            data-precio="<?= $prod['precio'] ?>"
                                                            data-stock="<?= $prod['stock_actual'] ?>"
                                                            data-stockmin="<?= $prod['stock_minimo'] ?>"
                                                            title="Editar Producto">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>

                                                    <form method="POST" action="index.php?estado_ver=1" onsubmit="return confirm('¿Deseas desactivar este producto?');" style="display:inline;">
                                                        <input type="hidden" name="accion" value="cambiar_estado">
                                                        <input type="hidden" name="id_producto" value="<?= $prod['id_producto'] ?>">
                                                        <input type="hidden" name="nuevo_estado" value="0">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Desactivar Producto">
                                                            <i class="bi bi-eye-slash-fill"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($rol_usuario === 'admin'): ?>
                                                    <form method="POST" action="index.php?estado_ver=0" onsubmit="return confirm('¿Deseas reactivar este producto?');" style="display:inline;">
                                                        <input type="hidden" name="accion" value="cambiar_estado">
                                                        <input type="hidden" name="id_producto" value="<?= $prod['id_producto'] ?>">
                                                        <input type="hidden" name="nuevo_estado" value="1">
                                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Reactivar Producto">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Reactivar
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <?= $ver_estado == 1 ? 'No hay productos activos registrados.' : 'No hay productos desactivados.' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- MODAL HISTORIAL DE MOVIMIENTOS -->
    <div class="modal fade" id="modalHistorial" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Historial de Movimientos de Stock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-striped align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th>Tipo</th>
                                    <th>Cantidad</th>
                                    <th>Usuario</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($movimientos_historial) > 0): ?>
                                    <?php foreach ($movimientos_historial as $m): ?>
                                        <tr>
                                            <td><small><?= isset($m['fecha']) ? date('d/m/Y H:i', strtotime($m['fecha'])) : 'N/A' ?></small></td>
                                            <td><strong><?= htmlspecialchars($m['producto_codigo']) ?></strong> - <?= htmlspecialchars($m['producto_nombre']) ?></td>
                                            <td>
                                                <!-- CÓDIGO CORREGIDO Y FLEXIBLE: -->
                                            <?php $es_entrada = (strcasecmp(trim($m['tipo']), 'Entrada') === 0); ?>
                                            <span class="badge bg-<?= $es_entrada ? 'success' : 'danger' ?>">
                                        <?= $es_entrada ? '➕ Entrada' : '➖ Salida' ?>
                                        </span>
                                            </td>
                                            <td><strong><?= $m['cantidad'] ?></strong></td>
                                            <td><small class="text-muted"><?= htmlspecialchars($m['usuario_nombre'] ?? 'Sistema') ?></small></td>
                                            <td><small><?= htmlspecialchars($m['observacion'] ?? '-') ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Aún no hay movimientos de stock registrados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CREAR CATEGORÍA -->
    <?php if ($rol_usuario === 'admin'): ?>
    <div class="modal fade" id="modalCrearCategoria" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form method="POST" action="index.php?estado_ver=<?= $ver_estado ?>">
                    <input type="hidden" name="accion" value="crear_categoria">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="bi bi-tags-fill me-2"></i>Nueva Categoría</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nombre de Categoría *</label>
                            <input type="text" name="nombre_categoria" class="form-control" placeholder="Ej: Teclados" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm">Guardar Categoría</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL MOVIMIENTO DE STOCK -->
    <div class="modal fade" id="modalMovimiento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="index.php?estado_ver=<?= $ver_estado ?>">
                    <input type="hidden" name="accion" value="movimiento">
                    <input type="hidden" name="id_producto" id="mov_id_producto">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="bi bi-arrow-down-up me-2"></i>Registrar Movimiento de Stock</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Producto</label>
                            <input type="text" id="mov_nombre_producto" class="form-control bg-light" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock Actual</label>
                            <input type="text" id="mov_stock_actual" class="form-control bg-light" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Movimiento *</label>
                            <select name="tipo_movimiento" class="form-select" required>
                                <option value="Entrada">➕ Entrada (Ingreso de inventario)</option>
                                <option value="Salida">➖ Salida (Venta o retiro)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cantidad *</label>
                            <input type="number" name="cantidad" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo / Observación</label>
                            <input type="text" name="motivo" class="form-control" placeholder="Ej: Venta boleta #123, Reposición proveedor...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info text-white">Guardar Movimiento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL CREAR PRODUCTO -->
    <?php if ($rol_usuario === 'admin'): ?>
    <div class="modal fade" id="modalCrearProducto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="index.php?estado_ver=<?= $ver_estado ?>">
                    <input type="hidden" name="accion" value="crear">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Agregar Nuevo Producto</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Código del Producto (Autogenerado)</label>
                            <input type="text" name="codigo" class="form-control bg-light" value="<?= $codigo_siguiente ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre del Producto *</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ej: Teclado Mecánico RGB" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="2" placeholder="Ej: Teclado gamer con switches azules"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Categoría *</label>
                            <select name="id_categoria" class="form-select" required>
                                <option value="">-- Selecciona una categoría --</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Precio ($) *</label>
                            <input type="number" name="precio" class="form-control" min="0" placeholder="Ej: 45000" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Stock Actual *</label>
                                <input type="number" name="stock_actual" class="form-control" min="0" value="10" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Stock Mínimo *</label>
                                <input type="number" name="stock_minimo" class="form-control" min="0" value="5" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Guardar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR PRODUCTO -->
    <div class="modal fade" id="modalEditarProducto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="index.php?estado_ver=<?= $ver_estado ?>">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id_producto" id="edit_id_producto">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Producto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Código del Producto (No modificable)</label>
                            <input type="text" id="edit_codigo" class="form-control bg-light" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre del Producto *</label>
                            <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Categoría *</label>
                            <select name="id_categoria" id="edit_id_categoria" class="form-select" required>
                                <option value="">-- Selecciona una categoría --</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Precio ($) *</label>
                            <input type="number" name="precio" id="edit_precio" class="form-control" min="0" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Stock Actual *</label>
                                <input type="number" name="stock_actual" id="edit_stock_actual" class="form-control" min="0" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Stock Mínimo *</label>
                                <input type="number" name="stock_minimo" id="edit_stock_minimo" class="form-control" min="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Actualizar Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Cargar datos en Modal Movimiento
        const modalMov = document.getElementById('modalMovimiento');
        if (modalMov) {
            modalMov.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('mov_id_producto').value = button.getAttribute('data-id');
                document.getElementById('mov_nombre_producto').value = button.getAttribute('data-nombre');
                document.getElementById('mov_stock_actual').value = button.getAttribute('data-stock');
            });
        }

        // Cargar datos en Modal Editar
        const modalEditar = document.getElementById('modalEditarProducto');
        if (modalEditar) {
            modalEditar.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('edit_id_producto').value = button.getAttribute('data-id');
                document.getElementById('edit_codigo').value = button.getAttribute('data-codigo');
                document.getElementById('edit_nombre').value = button.getAttribute('data-nombre');
                document.getElementById('edit_descripcion').value = button.getAttribute('data-descripcion');
                document.getElementById('edit_id_categoria').value = button.getAttribute('data-categoria');
                document.getElementById('edit_precio').value = button.getAttribute('data-precio');
                document.getElementById('edit_stock_actual').value = button.getAttribute('data-stock');
                document.getElementById('edit_stock_minimo').value = button.getAttribute('data-stockmin');
            });
        }
    </script>
</body>
</html>