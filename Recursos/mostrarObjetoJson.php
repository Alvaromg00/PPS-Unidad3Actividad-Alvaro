<?php
class User {
    private $username;
    private $isAdmin = false;
    private $cmd;

    public function __construct($username, $isAdmin, $cmd) {
        $this->username = $username;
        $this->isAdmin = $isAdmin;
        $this->cmd = $cmd;
    }

    public function __toString() {
        return "Usuario: {$this->username}<br>" .
               "Es administrador: " . ($this->isAdmin ? "Sí" : "No") . "<br>" .
               "Comando: " . htmlspecialchars($this->cmd);
    }
}

if (isset($_GET['data'])) {
    $json = $_GET['data'];

    $data = json_decode($json, true);

    // Validar que sea JSON válido
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON mal formado.";
        exit;
    }

    // Claves permitidas
    $clavesPermitidas = ['username', 'isAdmin', 'cmd'];
    $clavesRecibidas = array_keys($data);

    // Verificar si hay claves no permitidas
    $clavesNoPermitidas = array_diff($clavesRecibidas, $clavesPermitidas);

    if (!empty($clavesNoPermitidas)) {
        echo "Error: El JSON contiene claves no permitidas: ";
        echo "<pre>" . implode(", ", $clavesNoPermitidas) . "</pre>";
        exit;
    }

    // Validar tipos de datos
    if (!isset($data['username'], $data['isAdmin'], $data['cmd']) ||
        !is_string($data['username']) ||
        !is_bool($data['isAdmin']) ||
        !is_string($data['cmd'])) {
        echo "Datos inválidos.";
        exit;
    }

    // Crear el objeto
    $user = new User($data['username'], $data['isAdmin'], $data['cmd']);

    echo "<h3>Datos recibidos:</h3>";
    echo "<pre>{$user}</pre>";
} else {
    echo "No se proporciona ningún dato.";
}
