<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Acceso denegado</title>

<link rel="stylesheet" href="/docentes/frontend/css/app.css">

<style>
body{
display:flex;
align-items:center;
justify-content:center;
height:100vh;
background:var(--bg);
color:var(--text);
font-family:sans-serif;
}

.card{
background:linear-gradient(145deg,var(--card2),var(--card));
border:1px solid var(--line);
padding:40px;
border-radius:18px;
text-align:center;
box-shadow:0 10px 40px rgba(0,0,0,.4);
max-width:420px;
}

h1{
font-size:28px;
margin-bottom:10px;
}

p{
color:var(--muted);
margin-bottom:20px;
}

a{
display:inline-block;
padding:10px 16px;
background:linear-gradient(180deg,var(--accent),var(--accent-dark));
color:var(--bg);
font-weight:700;
border-radius:10px;
text-decoration:none;
}
</style>
</head>

<body>

<div class="card">

<img src="/docentes/frontend/images/1.png" width="60">

<h1>No tienes acceso</h1>

<p>
Esta página está protegida.<br>
Si crees que es un error contacta al administrador.
</p>

<a href="/docentes/public/">Volver al sistema</a>

</div>

</body>
</html>
