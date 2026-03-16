test.php<?php
require_once 'config/database.php';

// Test ket noi
echo "Ket noi: OK <br>";

// Test query
$result = mysqli_query($conn, "SELECT * FROM users");
$users  = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

echo "So nguoi dung: " . count($users) . "<br>";
echo "<pre>";
print_r($users);
echo "</pre>";
?>
```

Truy cập:
```
http://localhost/HETHONGQUANLYNGHIPHEPV/backend/test.php
```

---

## Kết quả mong đợi
```
Ket noi: OK
So nguoi dung: 4
Array (
    [0] => Array ( [id] => 1 [full_name] => HR Admin ... )
    [1] => Array ( [id] => 2 [full_name] => Manager IT ... )
    ...
)