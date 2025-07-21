<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Televlnka</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="assets/styles/mainStylesheet.css">
    <script src="assets/scripts/index.js"></script>

</head>
<body>
    <nav id="mainNav"></nav>
	<h1>My totally not placeholder site</h1>
	<?php
		echo "<p>".date_diff(date_create("2025-02-17"), date_create(date("Y-m-d")))->format("%a dnů")." bez úpravy. Ale doménu mám!</p>";
	?>
	<div class="image">
		<img src="dasenka.jpg" alt="Dášenkaaaaaa :D">
		<p>Co teď?</p>
	</div>
    <script src="assets/scripts/index.tsx"></script>

</body>