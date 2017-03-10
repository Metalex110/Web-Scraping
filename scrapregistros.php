<?php

require 'simple_html_dom.php';
$proxy = '127.0.0.1:9050';

//CONEXION BBDD
	
try{
	$conexion = new PDO('mysql:host=IP_SERVIDOR;dbname=BASE_DE_DATOS', "USUARIO", "CONTRASEÑA");  
	$conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} 
catch(PDOException $e){
	//Descomentar esta linea para ver errores de sql
	echo "ERROR: " . $e->getMessage();
}

//Preparamos la inserción de los datos que obtendremos a continuación
$insertar=$conexion->prepare("INSERT INTO empresas (nombre,cif,telefono,registro_mercantil,direccion,web,sector,empleados,presidente,antiguedad) 
							  VALUES(:nombre,:cif,:telefono,:registro_mercantil,:direccion,:web,:sector,:empleados,:presidente,:antiguedad);");

for($i=1;$i<=28863;$i++){

	//Cargamos las URL almacenadas con SCRAPURL.PHP
	$mostrarEnlaces=$conexion->prepare("select * from empresas_url where id=$i");
	$mostrarEnlaces->execute();
	$resultado = $mostrarEnlaces->fetchAll();

	//Generamos un bucle para recorrer cada URL
    foreach ($resultado as $row) {

        $id=$row['id'];
        $url=$row['nombre'];

		//Iniciamos la conexión cURL
		$ch2 = curl_init(); 
		//Configuramos nuestro proxy
		curl_setopt($ch2, CURLOPT_PROXY, $proxy);
		curl_setopt($ch2, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
		//Definimos la URL donde realizar SCRAPING
		curl_setopt($ch2,CURLOPT_URL,$url);
		//Seleccionamos un user-agent cambiante
		curl_setopt($ch2,CURLOPT_USERAGENT,rotarUser());
		//Tiempo máximo de la conexión
		curl_setopt($ch2,CURLOPT_TIMEOUT, 10);
		//Seguir redireccionamiento
		curl_setopt($ch2,CURLOPT_FOLLOWLOCATION, 1);
		//Retornar resultado de la petición
		curl_setopt($ch2,CURLOPT_RETURNTRANSFER, 1);

		//Deshabilitamos los errores de libxml
		libxml_use_internal_errors(true);

		//Almacenamos el HTML que nos envía el servidor y lo codificamos en UTF-8
		$result2 = utf8_encode(curl_exec($ch2));

		//Cerramos la conexion CURL. 
		curl_close($ch2); 

		// Creamos el objeto DOM
		$dom2 = new simple_html_dom();

		// Cargamos el HTML resultado de la petición cURL
		$dom2->load($result2);

		//Filtramos unicamente el contenedor DIV con las clases definidas
	 	$postscif = $dom2->find('div [class=clearfix container pt20]');

	 	//Por cada página...
		foreach ($postscif as $postaux) {

			//Almacenamos en variables unicamente la información que nos interesa parseando el código HTML
			$nombre = $postaux->find('h1',0);
			$cif =$postaux->find('h2',1);
			$direccion = $postaux->find('p',2);
			$descripcion = $postaux->find('p',0);
			$antiguedad = $postaux->find('p',1);
			$telefono = $postaux->find('p',3);
			$web = $postaux->find('p',5);
			$registroMercantil = $postaux->find('p',4);
			$sector = $postaux->find('p',6);
			$empleados = $postaux->find('p',7);
			$presidente = $postaux->find('p',8);

			//Limpiamos la información obtenida
			$empleadosSin=str_replace(".","",strip_tags($empleados));
			$empleadosSin2=str_replace("-","0",strip_tags($empleadosSin));
			$empleadosnum=(int)$empleadosSin2;

			//Preparamos los valores para la inserción
			$insertar->bindValue(':nombre', strip_tags($nombre));
	        $insertar->bindValue(':cif', strip_tags($cif));
	        $insertar->bindValue(':telefono', trim(strip_tags($telefono)));
	        $insertar->bindValue(':registro_mercantil', strip_tags(limpiarDatos($registroMercantil)));
	        $insertar->bindValue(':direccion', strip_tags(limpiarDatos($direccion)));
	        $insertar->bindValue(':web', trim(strip_tags($web)));
	        $insertar->bindValue(':sector', strip_tags(limpiarDatos($sector)));
	        $insertar->bindValue(':empleados', trim($empleadosnum));
	        $insertar->bindValue(':presidente', strip_tags(limpiarDatos($presidente)));
	        $insertar->bindValue(':antiguedad', convertirFecha($antiguedad));

	        //Insertamos en nuestra tabla los valores que nos interesan.
	        $insertar->execute();
		}
	}	
}

function limpiarDatos($datos){

	$datos1=str_replace("&nbsp;","",$datos);
	$datos2=str_replace("     ","",$datos1);
	$datos3=str_replace("    ","",$datos2);
	$datos4=str_replace("Ver más","", $datos3);
	$datos5=str_replace("   ", "", $datos4);
	$datos6=preg_replace("[\n|\r|\n\r]","",$datos5);

	$datosLimpios=ltrim($datos6);
	
	return $datosLimpios;
}

function convertirFecha($dato){

	$antiguedad0=strip_tags(limpiarDatos($dato));
	$findme   = '(';
	$pos = strstr($antiguedad0, $findme);
	$antiguedad1=str_replace("(","",$pos);
	$antiguedad2=str_replace(")","",$antiguedad1);
	$antiguedad3=str_replace("/","",$antiguedad2);

	//ddmmaaaa
	if(strlen($antiguedad3)>=8){

		$dia=substr($antiguedad3, 0, 2);
		$mes=substr($antiguedad3, 2, 2);
		$anio=substr($antiguedad3, 4, 4);

		$fecha_antiguedad=$anio."-".$mes."-".$dia;

	}
	else {

		$fecha_antiguedad="1900-01-01";

	}
	return $fecha_antiguedad;
}

//Rotamos User Agent
function rotarUser(){

	$userAgents = array('Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.19 (KHTML, like Gecko) Chrome/1.0.154.53 Safari/525.19',
				'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.19 (KHTML, like Gecko) Chrome/1.0.154.36 Safari/525.19',
				'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.10 (KHTML, like Gecko) Chrome/7.0.540.0 Safari/534.10',
				'Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US) AppleWebKit/534.4 (KHTML, like Gecko) Chrome/6.0.481.0 Safari/534.4',
				'Mozilla/5.0 (Macintosh; U; Intel Mac OS X; en-US) AppleWebKit/533.4 (KHTML, like Gecko) Chrome/5.0.375.86 Safari/533.4',
				'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/532.2 (KHTML, like Gecko) Chrome/4.0.223.3 Safari/532.2',
				'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/532.0 (KHTML, like Gecko) Chrome/4.0.201.1 Safari/532.0',
				'Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US) AppleWebKit/532.0 (KHTML, like Gecko) Chrome/3.0.195.27 Safari/532.0',
				'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/530.5 (KHTML, like Gecko) Chrome/2.0.173.1 Safari/530.5',
				'Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US) AppleWebKit/534.10 (KHTML, like Gecko) Chrome/8.0.558.0 Safari/534.10',
				'Mozilla/5.0 (X11; U; Linux x86_64; en-US) AppleWebKit/540.0 (KHTML,like Gecko) Chrome/9.1.0.0 Safari/540.0',
				'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/534.14 (KHTML, like Gecko) Chrome/9.0.600.0 Safari/534.14',
				'Mozilla/5.0 (X11; U; Windows NT 6; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.587.0 Safari/534.12',
				'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.13 (KHTML, like Gecko) Chrome/9.0.597.0 Safari/534.13',
				'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.16 (KHTML, like Gecko) Chrome/10.0.648.11 Safari/534.16',
				'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US) AppleWebKit/534.20 (KHTML, like Gecko) Chrome/11.0.672.2 Safari/534.20',
				'Mozilla/5.0 (Windows NT 6.0) AppleWebKit/535.1 (KHTML, like Gecko) Chrome/14.0.792.0 Safari/535.1',
				'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/535.2 (KHTML, like Gecko) Chrome/15.0.872.0 Safari/535.2',
				'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.36 Safari/535.7',
				'Mozilla/5.0 (Windows NT 6.0; WOW64) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.66 Safari/535.11',
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/535.19 (KHTML, like Gecko) Chrome/18.0.1025.45 Safari/535.19',
				'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/535.24 (KHTML, like Gecko) Chrome/19.0.1055.1 Safari/535.24',
				'Mozilla/5.0 (Windows NT 6.2) AppleWebKit/536.6 (KHTML, like Gecko) Chrome/20.0.1090.0 Safari/536.6',
				'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/22.0.1207.1 Safari/537.1',
				'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.15 (KHTML, like Gecko) Chrome/24.0.1295.0 Safari/537.15',
				'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.93 Safari/537.36',
				'Mozilla/5.0 (Windows NT 6.2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1467.0 Safari/537.36',
				'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.101 Safari/537.36',
				'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1623.0 Safari/537.36',
				'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.116 Safari/537.36',
				'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.103 Safari/537.36',
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.38 Safari/537.36',
				'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.71 Safari/537.36',
				'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36');

	//Devolvemos un User Agent aleatorio del Array
	return $userAgents[array_rand($userAgents)];
}
?>