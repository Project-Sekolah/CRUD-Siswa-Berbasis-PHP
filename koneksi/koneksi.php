<?php
// =======================
// 1. Konfigurasi koneksi
// =======================
$host = "127.0.0.1:3307";
$user = "root";
$password = "";
$dbname = "sekolahku";

// 2. Koneksi ke MySQL (tanpa DB dulu)
$conn = new mysqli($host, $user, $password);

// Cek koneksi awal
if ($conn->connect_error) {
  die("Koneksi gagal: " . $conn->connect_error);
}

// 3. Cek dan buat database jika belum ada
$sqlCreateDb = "CREATE DATABASE IF NOT EXISTS `$dbname`";
if ($conn->query($sqlCreateDb) === true) {
  //echo "Database dicek/dibuat.<br>";
} else {
  die("Gagal membuat database: " . $conn->error);
}

// 4. Pilih database
$conn->select_db($dbname);

// 5. Cek dan buat tabel siswa jika belum ada
$sqlCreateTable = "CREATE TABLE IF NOT EXISTS `siswa` (
  `nisn` VARCHAR(20) NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `kelamin` ENUM('Pria','Wanita') NOT NULL,
  `tempatLahir` VARCHAR(100) DEFAULT NULL,
  `tanggalLahir` DATE DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`nisn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sqlCreateTable) === true) {
  // echo "Tabel `siswa` dicek/dibuat.<br>";
} else {
  die("Gagal membuat tabel: " . $conn->error);
}

// =======================
// 6. Fungsi CRUD Siswa
// =======================

function tambahSiswa(
  $conn,
  $nisn,
  $nama,
  $kelamin,
  $tempatLahir,
  $tanggalLahir,
  $image = null
) {
  $stmt = $conn->prepare(
    "INSERT INTO siswa (nisn, nama, kelamin, tempatLahir, tanggalLahir, image) VALUES (?, ?, ?, ?, ?, ?)"
  );
  $stmt->bind_param(
    "ssssss",
    $nisn,
    $nama,
    $kelamin,
    $tempatLahir,
    $tanggalLahir,
    $image
  );
  return $stmt->execute();
}

function lihatSemuaSiswa($conn, $limit = 10, $offset = 0)
{
  $stmt = $conn->prepare("SELECT * FROM siswa LIMIT ? OFFSET ?");
  $stmt->bind_param("ii", $limit, $offset);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->fetch_all(MYSQLI_ASSOC);
}


function totalSiswa($conn)
{
  $result = $conn->query("SELECT COUNT(*) as total FROM siswa");
  $data = $result->fetch_assoc();
  return $data['total'];
}


function updateSiswa(
  $conn,
  $nisn,
  $nama,
  $kelamin,
  $tempatLahir,
  $tanggalLahir,
  $image = null
) {
  $stmt = $conn->prepare(
    "UPDATE siswa SET nama = ?, kelamin = ?, tempatLahir = ?, tanggalLahir = ?, image = ? WHERE nisn = ?"
  );
  $stmt->bind_param(
    "ssssss",
    $nama,
    $kelamin,
    $tempatLahir,
    $tanggalLahir,
    $image,
    $nisn
  );

  if (!$stmt->execute()) {
    error_log("Error updating record: " . $conn->error);
    return false;
  }
  return true;
}

function hapusSiswa($conn, $nisn)
{
  $stmt = $conn->prepare("DELETE FROM siswa WHERE nisn = ?");
  $stmt->bind_param("s", $nisn);
  return $stmt->execute();
}

function getSiswaByNISN($conn, $nisn)
{
  $sql = "SELECT * FROM siswa WHERE nisn = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $nisn);
  $stmt->execute();
  $result = $stmt->get_result();
  return $result->fetch_assoc(); // Mengembalikan data siswa dalam bentuk array asosiatif
}
