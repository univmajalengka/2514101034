<?php
mysqli_report(MYSQLI_REPORT_OFF);

// Membuat koneksi ke database (sesuaikan setelah database dibuat)
$servername = "localhost";
$username = "username";
$password = "password";
$dbname = "database_name";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Prosedur menghitung diskon berdasarkan total belanja
function hitungDiskon($totalBelanja)
{
    if ($totalBelanja >= 100000) {
        $persenDiskon = 10; // diskon 10%
    } elseif ($totalBelanja >= 50000) {
        $persenDiskon = 5;  // diskon 5%
    } else {
        $persenDiskon = 0;  // tidak ada diskon
    }

    // mengembalikan nilai diskon (dalam rupiah)
    return $totalBelanja * $persenDiskon / 100;
}

// Format angka ke Rupiah
function rupiah($angka)
{
    return "Rp. " . number_format($angka, 0, ",", ".");
}

// Contoh bawaan: Rp. 120.000 (bisa diganti lewat form)
$totalBelanja = 120000;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["total"])) {
    $totalBelanja = max(0, (float) $_POST["total"]);
}

$diskon = hitungDiskon($totalBelanja);
$totalBayar = $totalBelanja - $diskon;
$persen = $totalBelanja > 0 ? round($diskon / $totalBelanja * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hitung Diskon Belanja</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="card">
        <h1>Hitung Diskon Belanja</h1>
        <p class="sub">Masukkan total belanja untuk melihat diskon.</p>

        <form method="post">
            <label for="total">Total Belanja (Rp)</label>
            <input type="number" id="total" name="total" min="0" step="1000"
                   value="<?= htmlspecialchars((string) $totalBelanja) ?>" required>
            <button type="submit">Hitung</button>
        </form>

        <div class="hasil">
            <div class="baris">
                <span>Total Belanja</span>
                <span><?= rupiah($totalBelanja) ?></span>
            </div>
            <div class="baris diskon">
                <span>Diskon <?php if ($persen > 0): ?><span class="badge"><?= $persen ?>%</span><?php endif; ?></span>
                <span>- <?= rupiah($diskon) ?></span>
            </div>
            <div class="baris total">
                <span>Total Bayar</span>
                <span><?= rupiah($totalBayar) ?></span>
            </div>
        </div>

        <div class="aturan">
            <strong>Ketentuan diskon:</strong><br>
            &ge; Rp. 100.000 &rarr; diskon 10%<br>
            &ge; Rp. 50.000 &rarr; diskon 5%<br>
            &lt; Rp. 50.000 &rarr; tidak ada diskon
        </div>
    </div>
</body>
</html>