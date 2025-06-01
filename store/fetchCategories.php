<?php
// store/fetchCategories.php
require_once '../config/database.php';

$sql = "SELECT category_id, name FROM CATEGORIES ORDER BY name ASC";

$result = $mysqli->query($sql);

$categories = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($categories);

$mysqli->close();
