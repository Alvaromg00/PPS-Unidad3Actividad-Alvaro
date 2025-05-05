<?php
class User {
    public $username;
    public $isAdmin = false;

    public function __destruct() {
        if (!empty($this->cmd)) {
            echo "<pre>Ejecutando comando (simulado): {$this->cmd}</pre>";
            // system($this->cmd); // ← mantener comentado para pruebas seguras
        }
    }
}

if (isset($_GET['data'])) {
    $data = $_GET['data'];

    // Deserialización segura: solo se permite la clase User
    $obj = @unserialize($data, ['allowed_classes' => ['User']]);

    if (!$obj instanceof User) {
        echo "Error: El objeto deserializado no es de tipo User.";
        exit;
    }

    // Propiedades permitidas
    $propiedadesPermitidas = ['username', 'isAdmin'];

    // Obtener propiedades reales del objeto deserializado
    $propiedadesObjeto = array_keys(get_object_vars($obj));

    // Verificar que no hay propiedades adicionales
    $propiedadesExtra = array_diff($propiedadesObjeto, $propiedadesPermitidas);

    if (!empty($propiedadesExtra)) {
        echo "<h3>Error:</h3>";
        echo "El objeto contiene propiedades no permitidas: <pre>" . implode(", ", $propiedadesExtra) . "</pre>";
        exit;
    }

    // Validar tipos de propiedades
    $errores = [];

    if (!isset($obj->username) || !is_string($obj->username)) {
        $errores[] = "El campo 'username' no está definido o no es una cadena.";
    }

    if (!isset($obj->isAdmin) || !is_bool($obj->isAdmin)) {
        $errores[] = "El campo 'isAdmin' no está definido o no es booleano.";
    }


    if (!empty($errores)) {
        echo "<h3>Errores de validación:</h3><ul>";
        foreach ($errores as $e) {
            echo "<li>" . htmlspecialchars($e) . "</li>";
        }
        echo "</ul>";
        exit;
    }

    echo "<h3>Objeto deserializado válidamente:</h3>";
    echo "<pre>";
    print_r($obj);
    echo "</pre>";

    // Forzar destrucción
    unset($obj);
} else {
    echo "No se proporciona ningún dato.";
}
