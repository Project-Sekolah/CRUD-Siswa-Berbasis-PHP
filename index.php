<?php
  include "koneksi/koneksi.php"; // Sertakan koneksi database

  // Inisialisasi pesan
  $pesan = "";

  // Cek apakah ada request form
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nisn          = $_POST["nisn"] ?? "";
    $nama          = $_POST["nama"] ?? "";
    $kelamin       = $_POST["jk"] ?? "";
    $tempatLahir   = $_POST["tempat-lahir"] ?? "";
    $tanggalLahir  = $_POST["tanggal"] ?? "";
    $aksi          = $_POST["aksi"] ?? "";
    $fotoLama      = $_POST["foto_lama"] ?? null;
    $imagePath     = null;

    // Upload foto jika ada file
    if (!empty($_FILES["photo"]["tmp_name"])) {
      $uploadDir = "uploads/";
      if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
      }

      $ext = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
      $allowedExt = ["jpg", "jpeg", "png", "gif"];

      if (in_array($ext, $allowedExt)) {
        $fileName   = $nisn . "." . $ext;
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {
          $imagePath = $fileName;

          // Hapus foto lama jika nama file berbeda
          if ($fotoLama && $fotoLama !== $fileName && file_exists($uploadDir . $fotoLama)) {
            unlink($uploadDir . $fotoLama);
          }
        } else {
          $pesan = "Gagal mengunggah foto.";
        }
      } else {
        $pesan = "Ekstensi file tidak didukung. Hanya jpg, jpeg, png, gif.";
      }
    }

    // Proses aksi
    switch ($aksi) {
      case "tambah":
        if ($nisn && $nama && $kelamin) {
          $hasil = tambahSiswa($conn, $nisn, $nama, $kelamin, $tempatLahir, $tanggalLahir, $imagePath);
          $pesan = $hasil ? "Data berhasil ditambahkan!" : "Gagal menambahkan data.";
        } else {
          $pesan = "Harap isi semua field yang wajib!";
        }
        break;

      case "update":
        if ($nisn && $nama && $kelamin) {
          $hasil = updateSiswa($conn, $nisn, $nama, $kelamin, $tempatLahir, $tanggalLahir, $imagePath ?? $fotoLama);
          $pesan = $hasil ? "Data berhasil diupdate!" : "Gagal mengupdate data.";
        } else {
          $pesan = "Harap isi semua field yang wajib!";
        }
        break;

      case "hapus":
        if ($nisn) {
          $siswa = getSiswaByNISN($conn, $nisn);
          if (!$siswa) {
            $pesan = "Data dengan NISN $nisn tidak ditemukan!";
            break;
          }

          if (hapusSiswa($conn, $nisn)) {
            if ($siswa["image"] && file_exists("uploads/" . $siswa["image"])) {
              unlink("uploads/" . $siswa["image"]);
            }
            $pesan = "Data berhasil dihapus!";
          } else {
            $pesan = "Gagal menghapus data.";
          }
        } else {
          $pesan = "Harap pilih data yang akan dihapus!";
        }
        break;
    }
  }

  // Fungsi untuk mengisi form saat edit
  function isiForm($nisn)
  {
    global $conn;
    $result = $conn->query("SELECT * FROM siswa WHERE nisn = '$nisn'");
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
  }

  // Proses pencarian data untuk edit
  if (isset($_POST["cari"])) {
    $nisnCari = $_POST["nisn"] ?? "";
    $_SESSION["nisn_input"] = $nisnCari;

    if ($nisnCari) {
      $siswa = isiForm($nisnCari);
      if ($siswa) {
        $_SESSION["edit"] = $siswa;
        unset($_SESSION["nisn_input"]);
      } else {
        $pesan = "Data tidak ditemukan!";
      }
    } else {
      $pesan = "Harap masukkan NISN!";
    }
  }

  // Handle request AJAX untuk pengecekan NISN
  if (isset($_POST["cek_nisn"])) {
    $nisn = $_POST["cek_nisn"];
    $stmt = $conn->prepare("SELECT COUNT(*) FROM siswa WHERE nisn = ?");
    $stmt->bind_param("s", $nisn);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    echo json_encode(["exists" => $count > 0]);
    exit();
  }
?>

<!doctype html>
<html lang="id">

  <head>
    <meta charset="UTF-8" />
    <title>Form Siswa</title>
    <link rel="stylesheet" href="assets/style.css">
  </head>

  <body>
    <div class="container">
        <!-- PESAN -->
      <?php if ($pesan): ?>
        <div class="pesan <?= stripos($pesan, "gagal") !== false ? "gagal" : "sukses"; ?>">
          <?= $pesan; ?>
        </div>
      <?php endif; ?>

      <h2>FORM SISWA</h2>

      <form id="form-siswa" method="POST" enctype="multipart/form-data">
        <div class="form-section">
          <div class="form-left">
            <!-- NISN -->
            <label for="nisn">NISN</label>
            <div class="nisn-group">
              <input type="text" id="nisn" name="nisn" placeholder="Masukkan NISN"
                value="<?= isset($_SESSION['edit']) ? $_SESSION['edit']['nisn'] : ($_SESSION['nisn_input'] ?? '') ?>">
              <button type="submit" name="cari">Cari Data</button>
            </div>

            <!-- Nama -->
            <label for="nama">Nama Lengkap</label>
            <input readonly type="text" id="nama" name="nama" placeholder="Masukkan Nama Lengkap"
              value="<?= $_SESSION["edit"]["nama"] ?? '' ?>">

            <!-- Jenis Kelamin -->
            <label for="jk">Jenis Kelamin</label>
            <select disabled id="jk" name="jk">
              <option value="">Pilih Salah Satu</option>
              <option value="Pria" <?= (isset($_SESSION["edit"]) && $_SESSION["edit"]["kelamin"] == "Pria") ? "selected" : "" ?>>Pria</option>
              <option value="Wanita" <?= (isset($_SESSION["edit"]) && $_SESSION["edit"]["kelamin"] == "Wanita") ? "selected" : "" ?>>Wanita</option>
            </select>

            <!-- Tempat Lahir -->
            <label for="tempat-lahir">Tempat Lahir</label>
            <select disabled id="tempat-lahir" name="tempat-lahir">
              <option value="">Pilih Salah Satu</option>
              <?php
              $tempatOptions = [
                "Medan, Sumatera Utara", "Jakarta, DKI Jakarta", "Bandung, Jawa Barat", "Surabaya, Jawa Timur",
                "Yogyakarta, DI Yogyakarta", "Denpasar, Bali", "Makassar, Sulawesi Selatan",
                "Pontianak, Kalimantan Barat", "Padang, Sumatera Barat", "Palembang, Sumatera Selatan",
                "Banjarmasin, Kalimantan Selatan"
              ];
              foreach ($tempatOptions as $tempat) {
                $selected = (isset($_SESSION["edit"]) && $_SESSION["edit"]["tempatLahir"] == $tempat) ? "selected" : "";
                echo "<option value=\"$tempat\" $selected>$tempat</option>";
              }
              ?>
            </select>

            <!-- Tanggal Lahir -->
            <label for="tanggal">Tanggal Lahir</label>
            <?php
            $tanggal = isset($_SESSION["edit"])
              ? date("Y-m-d", strtotime($_SESSION["edit"]["tanggalLahir"]))
              : date("Y-m-d");
            ?>
            <div style="display: flex; align-items: center; gap: 10px;">
              <input readonly type="date" id="tanggal" name="tanggal" value="<?= $tanggal ?>">
              <span style="width: 100px;" id="tanggal-terpilih"><?= date("d-m-Y", strtotime($tanggal)) ?></span>
            </div>

            <!-- Foto -->
            <label for="photo">Foto</label>
            <div class="photo-wrapper">
              <input disabled type="file" id="photo" name="photo" />
              <img src="<?= isset($_SESSION["edit"]["image"]) && $_SESSION["edit"]["image"] ? 'uploads/' . $_SESSION["edit"]["image"] : 'uploads/default.png' ?>" alt="Preview" class="photo-preview" />
            </div>

            <input type="hidden" name="foto_lama" id="foto_lama" value="<?= htmlspecialchars($_SESSION["edit"]["image"] ?? '') ?>">

            <p>Path: <span id="path"><?= isset($_SESSION["edit"]["image"]) ? "uploads/" . $_SESSION["edit"]["image"] : "" ?></span></p>
            <p>Nama: <span id="filename"><?= $_SESSION["edit"]["image"] ?? '' ?></span></p>
          </div>

          <!-- Tombol Aksi -->
          <div class="form-right">
            <div class="button-group">
              <button class="tambahData" type="button">Tambah Data</button>
              <button type="submit" name="aksi" value="tambah" class="simpanBtn">Simpan Data</button>
              <button class="editBtn" type="button">Edit Data</button>
              <button type="submit" name="aksi" value="update" class="simpanEditBtn">Simpan Edit</button>
              <button type="button" id="hapusBtn">Hapus</button>
              <button type="button" id="batalBtn">Batal</button>
              <button type="button" id="keluarBtn">Keluar Form</button>
            </div>
          </div>
        </div>
      </form>

      <!-- Filter dan Pencarian -->
      <h3>Tabel Data Siswa:</h3>
      <?php
      $perPage = 5 ;
      $totalData = totalSiswa($conn);
      $totalPages = ceil($totalData / $perPage);
      $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
      $offset = ($page - 1) * $perPage;

      $siswaList = [];

      if (isset($_POST['batal'])) {
        unset($_POST['keyword']);
        $result = $conn->query("SELECT * FROM siswa");
        $siswaList = $result->fetch_all(MYSQLI_ASSOC);
      } elseif (isset($_POST['cari'])) {
        $keyword = trim($_POST['keyword'] ?? '');
        if ($keyword !== '') {
          $like = "%" . $conn->real_escape_string($keyword) . "%";
          $sql = "SELECT * FROM siswa 
                  WHERE nisn LIKE ? 
                    OR nama LIKE ? 
                    OR kelamin LIKE ? 
                    OR tempatLahir LIKE ? 
                    OR tanggalLahir LIKE ?";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("sssss", $like, $like, $like, $like, $like);
          $stmt->execute();
          $result = $stmt->get_result();
          $siswaList = $result->fetch_all(MYSQLI_ASSOC);
        }
      } else {
        $result = $conn->query("SELECT * FROM siswa LIMIT $perPage OFFSET $offset");
        $siswaList = $result->fetch_all(MYSQLI_ASSOC);
      }
      ?>

      <p>Total Data: <?= $totalData ?></p>

      <form method="post" style="margin-bottom: 20px;">
        <input type="text" name="keyword" placeholder="Cari berdasarkan NISN, Nama, Kelamin, Tempat atau Tanggal Lahir"
          value="<?= $_POST['keyword'] ?? '' ?>" style="padding: 8px; width: 300px;">
        <button type="submit" name="cari" id="cariBtn">Cari Data</button>
        <button type="submit" name="batal" id="batalBtn">Batal</button>
      </form>

      <!-- Tabel Data -->
      <table>
        <thead>
          <tr>
            <th>NISN</th>
            <th>Nama</th>
            <th>Jenis Kelamin</th>
            <th>Tempat Lahir</th>
            <th>Tanggal Lahir</th>
            <th>Foto</th>
          </tr>
        </thead>
        <tbody id="siswa-table-body">
          <?php if (!empty($siswaList)): ?>
            <?php foreach ($siswaList as $siswa): ?>
              <tr data-nisn="<?= $siswa["nisn"] ?>"
                  data-nama="<?= $siswa["nama"] ?>"
                  data-kelamin="<?= $siswa["kelamin"] ?>"
                  data-tempatlahir="<?= $siswa["tempatLahir"] ?>"
                  data-tanggallahir="<?= $siswa["tanggalLahir"] ?>"
                  data-image="<?= $siswa["image"] ?>">
                <td><?= htmlspecialchars($siswa["nisn"]) ?></td>
                <td><?= htmlspecialchars($siswa["nama"]) ?></td>
                <td><?= htmlspecialchars($siswa["kelamin"]) ?></td>
                <td><?= htmlspecialchars($siswa["tempatLahir"]) ?></td>
                <td><?= htmlspecialchars(date("Y-m-d", strtotime($siswa["tanggalLahir"]))) ?></td>
                <td><img src="uploads/<?= htmlspecialchars($siswa["image"]) ?>" alt="Foto" class="photo-preview" /></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align: center;">Data tidak ditemukan.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>" <?= $i == $page ? 'style="font-weight:bold"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
        <?php endif; ?>
      </div>



          <!-- Modal Error -->
          <div id="modal-error" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#00000088; align-items:center; justify-content:center; z-index:9999;">
            <div style="background:white; padding:20px; border-radius:10px; max-width:400px; display:flex; align-items:center; gap:15px;">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="red" stroke-width="2" viewBox="0 0 24 24" width="30" height="30">
                <circle cx="12" cy="12" r="10" stroke="red" stroke-width="2" fill="none"/>
                <line x1="12" y1="8" x2="12" y2="13" stroke="red" stroke-width="2" />
                <circle cx="12" cy="17" r="1" fill="red"/>
              </svg>
              <p id="modal-error-message" style="margin:0 0 1rem 0; flex:1;">Terjadi kesalahan</p>
              <button onclick="closeErrorModal()">Tutup</button>
            </div>
          </div>

          <!-- Modal Info -->
          <div id="modal-info" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#00000088; align-items:center; justify-content:center; z-index:9999;">
            <div style="background:white; padding:20px; border-radius:10px; max-width:400px; display:flex; align-items:center; gap:15px;">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#007bff" stroke-width="2" viewBox="0 0 24 24" width="30" height="30">
                <circle cx="12" cy="12" r="10" stroke="#007bff" stroke-width="2" fill="none"/>
                <line x1="12" y1="16" x2="12" y2="12" stroke="#007bff" stroke-width="2" />
                <circle cx="12" cy="8" r="1" fill="#007bff"/>
              </svg>
              <p id="modal-info-message" style="margin:0 0 1rem 0; flex:1;">Informasi</p>
              <button onclick="closeInfoModal()">Tutup</button>
            </div>
          </div>

          <!-- modal hapus btn -->
          <div id="modal-confirm-hapus" class="modal" style="display: none; position: fixed; z-index: 999; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
            <div style="background: white; padding: 20px; border-radius: 10px; width: 300px; text-align: center; display:flex; flex-direction: column; align-items:center; gap:15px;">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="crimson" stroke-width="2" viewBox="0 0 24 24" width="40" height="40">
                <path d="M3 6h18" stroke="crimson" stroke-width="2" stroke-linecap="round"/>
                <path d="M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6" stroke="crimson" stroke-width="2" stroke-linecap="round"/>
                <path d="M10 11v6" stroke="crimson" stroke-width="2" stroke-linecap="round"/>
                <path d="M14 11v6" stroke="crimson" stroke-width="2" stroke-linecap="round"/>
              </svg>
              <p>Apakah Anda yakin ingin menghapus data ini?</p>
              <div style="margin-top: 20px;">
                <button id="confirmHapus" style="margin-right: 10px;">Ya, Hapus</button>
                <button onclick="closeHapusModal()">Batal</button>
              </div>
            </div>
          </div>

          <!-- Modal Konfirmasi Batal -->
          <div id="modal-confirm-batal" class="modal" style="display: none; position: fixed; z-index: 999; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
            <div style="background: white; padding: 20px; border-radius: 10px; width: 300px; text-align: center; display:flex; flex-direction: column; align-items:center; gap:15px;">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#ff9900" stroke-width="2" viewBox="0 0 24 24" width="40" height="40">
                <circle cx="12" cy="12" r="10" stroke="#ff9900" stroke-width="2" fill="none"/>
                <path d="M12 8v4" stroke="#ff9900" stroke-width="2" stroke-linecap="round"/>
                <circle cx="12" cy="16" r="1" fill="#ff9900"/>
              </svg>
              <p>Apakah Anda yakin ingin membatalkan perubahan?</p>
              <div style="margin-top: 20px;">
                <button id="confirmBatal" style="margin-right: 10px;">Ya, Batalkan</button>
                <button onclick="closeBatalModal()">Tidak</button>
              </div>
            </div>
          </div>

          <!-- Modal Konfirmasi Keluar -->
          <div id="modal-confirm-keluar" class="modal" style="display:none; position: fixed; z-index: 999; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
            <div style="background: white; padding: 20px; border-radius: 10px; width: 300px; text-align: center; display:flex; flex-direction: column; align-items:center; gap:15px;">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#444" stroke-width="2" viewBox="0 0 24 24" width="40" height="40">
                <path d="M9 18l6-6-6-6" stroke="#444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <p>Apakah Anda yakin ingin keluar dari form?</p>
              <div style="margin-top: 20px;">
                <button id="confirmKeluar" style="margin-right: 10px;">Ya, Keluar</button>
                <button onclick="closeKeluarModal()">Tidak</button>
              </div>
            </div>
    </div>
            
    <!-- ========== Modal Handling ========== -->
    <script>
      function showModal(id) {
        document.getElementById(id).style.display = "flex";
      }

      function closeModal(id) {
        document.getElementById(id).style.display = "none";
      }

      function showInfoModal(message) {
        document.getElementById("modal-info-message").textContent = message;
        showModal("modal-info");
      }

      function closeInfoModal() {
        closeModal("modal-info");
      }

      function showErrorModal(message) {
        document.getElementById("modal-error-message").textContent = message;
        showModal("modal-error");
      }

      function closeErrorModal() {
        closeModal("modal-error");
      }

      function showHapusModal() {
        showModal("modal-confirm-hapus");
      }

      function closeHapusModal() {
        closeModal("modal-confirm-hapus");
      }

      function showBatalModal() {
        showModal("modal-confirm-batal");
      }

      function closeBatalModal() {
        closeModal("modal-confirm-batal");
      }

      function showKeluarModal() {
        showModal("modal-confirm-keluar");
      }

      function closeKeluarModal() {
        closeModal("modal-confirm-keluar");
      }
    </script>

    <!-- ========== Event Listener Utama ========== -->
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        const hapusBtn = document.getElementById("hapusBtn");
        const confirmHapus = document.getElementById("confirmHapus");
        const batalBtn = document.getElementById("batalBtn");
        const confirmBatal = document.getElementById("confirmBatal");
        const keluarBtn = document.getElementById("keluarBtn");
        const confirmKeluar = document.getElementById("confirmKeluar");
        const formSiswa = document.getElementById("form-siswa");
        const nisnInput = document.getElementById("nisn");

        // Tombol Hapus
        if (hapusBtn && confirmHapus && formSiswa) {
          hapusBtn.addEventListener("click", () => {
            if (!nisnInput.value.trim()) {
              showInfoModal("Silakan isi NISN terlebih dahulu sebelum menghapus!");
              return;
            }
            showHapusModal();
          });

          confirmHapus.addEventListener("click", () => {
            const aksiInput = document.createElement("input");
            aksiInput.type = "hidden";
            aksiInput.name = "aksi";
            aksiInput.value = "hapus";
            formSiswa.appendChild(aksiInput);
            formSiswa.submit();
          });
        }

        // Tombol Batal
        if (batalBtn && confirmBatal) {
          batalBtn.addEventListener("click", () => {
            if (!nisnInput.value.trim()) {
              showInfoModal("Belum ada data yang bisa dibatalkan!");
              return;
            }
            showBatalModal();
          });

          confirmBatal.addEventListener("click", () => {
            window.location.href = window.location.pathname;
          });
        }

        // Tombol Keluar
        if (keluarBtn && confirmKeluar) {
          keluarBtn.addEventListener("click", showKeluarModal);
          confirmKeluar.addEventListener("click", () => window.close());
        }
      });
    </script>

    <!-- ========== Preview Gambar ========== -->
    <script>
      const photoInput = document.getElementById("photo");
      const previewImg = document.querySelector(".photo-preview");
      const pathSpan = document.getElementById("path");
      const fileNameSpan = document.getElementById("filename");

      photoInput.addEventListener("change", function () {
        const file = this.files[0];
        if (file) {
          previewImg.src = URL.createObjectURL(file);
          pathSpan.textContent = "Path File: " + this.value;
          fileNameSpan.textContent = "Nama File: " + file.name;
        } else {
          previewImg.src = "foto-default.jpg";
          pathSpan.textContent = "Path File:";
          fileNameSpan.textContent = "Nama File:";
        }
      });
    </script>

    <!-- ========== Klik Baris Tabel untuk Edit ========== -->
    <script>
      const siswaTableBody = document.getElementById("siswa-table-body");

      siswaTableBody.addEventListener("click", function (e) {
        const row = e.target.closest("tr");
        if (!row) return;

        const nisn = row.getAttribute("data-nisn");
        const nama = row.getAttribute("data-nama");
        const kelamin = row.getAttribute("data-kelamin");
        const tempatLahir = row.getAttribute("data-tempatlahir");
        const tanggalLahir = row.getAttribute("data-tanggallahir");
        const image = row.getAttribute("data-image");

        document.getElementById("nisn").value = nisn;
        document.getElementById("nama").value = nama;
        document.getElementById("jk").value = kelamin;
        document.getElementById("tempat-lahir").value = tempatLahir;
        document.getElementById("tanggal").value = tanggalLahir;
        document.getElementById("photo").value = "";

        if (image) {
          document.querySelector(".photo-preview").src = "uploads/" + image;
          document.getElementById("foto_lama").value = image;
          pathSpan.textContent = "Path File: uploads/" + image;
          fileNameSpan.textContent = "Nama File: " + image;
        } else {
          previewImg.src = "foto-default.jpg";
          pathSpan.textContent = "Path File:";
          fileNameSpan.textContent = "Nama File:";
        }
      });
    </script>

    <!-- ========== Tambah dan Edit Mode ========== -->
    <script>
      function toggleFormFields(disableNISN = true) {
        const form = document.getElementById("form-siswa");
        if (!form) return;

        const fields = form.querySelectorAll("input, select, textarea");

        fields.forEach((el) => {
          if (el.id === "nisn") {
            el.readOnly = disableNISN;
          } else {
            el.readOnly = false;
            el.disabled = false;
          }
        });
      }

      document.addEventListener("DOMContentLoaded", function () {
        const tambahBtn = document.querySelector(".tambahData");
        const simpanBtn = document.querySelector(".simpanBtn");
        const editBtn = document.querySelector(".editBtn");
        const simpanEditBtn = document.querySelector(".simpanEditBtn");
        const nisnInput = document.getElementById("nisn");

        simpanBtn.style.display = "none";
        simpanEditBtn.style.display = "none";

        // Tombol Tambah
        if (tambahBtn && simpanBtn && nisnInput) {
          tambahBtn.addEventListener("click", function () {
            const nisn = nisnInput.value.trim();

            if (!nisn) {
              showErrorModal("Silakan isi NISN terlebih dahulu!");
              return;
            }

            fetch(window.location.href, {
              method: "POST",
              headers: {
                "Content-Type": "application/x-www-form-urlencoded",
              },
              body: "cek_nisn=" + encodeURIComponent(nisn),
            })
              .then((res) => {
                if (!res.ok) throw new Error("Status HTTP: " + res.status);
                return res.json();
              })
              .then((data) => {
                if (data.exists) {
                  showErrorModal("NISN sudah terdaftar. Silakan gunakan yang lain.");
                } else {
                  toggleFormFields(false);
                  simpanBtn.style.display = "block";
                  tambahBtn.style.display = "none";
                }
              })
              .catch((err) => {
                showErrorModal("Terjadi kesalahan: " + err.message);
                console.error("Detail error:", err);
              });
          });
        }

        // Tombol Edit
        if (editBtn && simpanEditBtn && nisnInput) {
          editBtn.addEventListener("click", function () {
            const nisn = nisnInput.value.trim();

            if (!nisn) {
              showErrorModal("Silakan isi NISN terlebih dahulu sebelum mengedit!");
              return;
            }

            try {
              toggleFormFields(true);
              simpanEditBtn.style.display = "block";
              editBtn.style.display = "none";
              showInfoModal("Mode edit diaktifkan. Silakan ubah data, lalu klik Simpan Edit.");
            } catch (error) {
              showErrorModal("Gagal mengaktifkan mode edit: " + error.message);
              simpanEditBtn.style.display = "none";
              editBtn.style.display = "block";
              console.error("Detail error:", error);
            }
          });
        }
      });
    </script>

    <!-- ========== Format Tanggal ========== -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
          const inputTanggal = document.getElementById("tanggal");
          const spanTanggal = document.getElementById("tanggal-terpilih");

          function formatTanggal(tanggalStr) {
            const tgl = new Date(tanggalStr);
            const hari = String(tgl.getDate()).padStart(2, "0");
            const bulan = String(tgl.getMonth() + 1).padStart(2, "0");
            const tahun = tgl.getFullYear();
            return `${hari}-${bulan}-${tahun}`;
          }

          inputTanggal.addEventListener("input", function () {
            spanTanggal.textContent = formatTanggal(this.value);
          });
        });
    </script>

  </body>
</html>