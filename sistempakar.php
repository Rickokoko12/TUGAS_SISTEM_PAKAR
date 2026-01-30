<?php
// ===============================
// SISTEM PAKAR DIAGNOSA MATA PADA ANAK - VERSI FINAL
// ===============================

$conn = new mysqli("localhost", "root", "", "sistem_pakar_mata");
if ($conn->connect_error) die("Koneksi gagal");

// Include RAG LLM
require_once "rag_openai.php";

// =======================================
// Fungsi NLP
// =======================================
function nlp($text, $conn){
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', '', $text);

    $stopwords = [
        "dan","yang","atau","saya","anak","itu","ini",
        "sering","agak","terasa","setelah","lama",
        "main","hp","gadget"
    ];

    $kataUser = array_diff(explode(" ", $text), $stopwords);
    $hasil = [];

    $q = $conn->query("SELECT id_gejala, nama_gejala FROM gejala");
    if (!$q) return $hasil;

    while($g = $q->fetch_assoc()){
        $kataGejala = explode(" ", strtolower($g['nama_gejala']));
        $cocok = array_intersect($kataUser, $kataGejala);
        if(count($cocok) > 0){
            $hasil[] = $g['id_gejala'];
        }
    }
    return array_unique($hasil);
}

// =======================================
// Forward Chaining
// =======================================
function forwardChaining($fakta, $conn){
    $hasil = [];
    $p = $conn->query("SELECT * FROM penyakit");
    if (!$p) return $hasil;

    while($row = $p->fetch_assoc()){
        $qTotal = $conn->query("SELECT COUNT(*) AS total FROM `rule` WHERE id_penyakit = {$row['id_penyakit']}");
        if (!$qTotal) continue;
        $total = $qTotal->fetch_assoc()['total'];
        if ($total == 0) continue;

        $cocok = 0;
        foreach($fakta as $id_gejala){
            $cek = $conn->query("SELECT 1 FROM `rule` WHERE id_penyakit = {$row['id_penyakit']} AND id_gejala = {$id_gejala}");
            if ($cek && $cek->num_rows > 0){
                $cocok++;
            }
        }

        if ($cocok > 0){
            $hasil[] = [
                "penyakit" => $row['nama_penyakit'],
                "solusi"   => $row['solusi'],
                "nilai"    => round(($cocok / $total) * 100)
            ];
        }
    }
    return $hasil;
}

// =======================================
// Main
// =======================================
$hasil = [];
$error = "";

if (isset($_POST['keluhan'])) {
    $keluhan = trim($_POST['keluhan']);
    if ($keluhan == "") {
        $error = "Keluhan tidak boleh kosong.";
    } else {
        $fakta = nlp($keluhan, $conn);
        if (empty($fakta)) {
            $error = "Gejala tidak dikenali oleh sistem.";
        } else {
            $hasil = forwardChaining($fakta, $conn);
            if (empty($hasil)) {
                $error = "Tidak ditemukan penyakit yang sesuai.";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Sistem Pakar Diagnosa Mata Anak</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{box-sizing:border-box}
body{
    margin:0;
    font-family:'Segoe UI',sans-serif;
    background:#f4f7fb;
    scroll-behavior:smooth;
}

/* NAVBAR */
nav{
    position:fixed;
    top:0; width:100%;
    background:white;
    padding:15px 40px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 5px 15px rgba(0,0,0,.08);
    z-index:100;
}
nav b{color:#5a55e6;font-size:18px}
nav a{
    margin-left:20px;
    text-decoration:none;
    color:#333;
    font-weight:500;
}
nav a{
    margin-left:20px;
    text-decoration:none;
    color:#333;
    font-weight:500;
    display:inline-flex;
    align-items:center;
    gap:6px;
}
nav a:hover{color:#5a55e6}

/* HERO */
.hero{
    min-height:100vh;
    padding:120px 8% 60px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    background:linear-gradient(135deg,#6aa8ff,#5a55e6);
    color:white;
}
.hero-text{max-width:520px; animation:slide 1s}
.hero h1{font-size:42px}
.hero p{font-size:18px; line-height:1.6}
.hero a{
    display:inline-block;
    margin-top:25px;
    padding:14px 30px;
    background:white;
    color:#5a55e6;
    border-radius:30px;
    font-weight:bold;
    text-decoration:none;
}
.hero img{
    max-width:420px;
    animation:fade 1.5s;
}

/* SECTION */
section{padding:80px 8%}
h2{text-align:center;color:#5a55e6}

/* CHATBOT */
.chat{
    max-width:600px;
    margin:auto;
    background:white;
    padding:30px;
    border-radius:15px;
    box-shadow:0 15px 30px rgba(0,0,0,.1);
}
input,button{
    width:100%;
    padding:12px;
    margin-top:15px;
    border-radius:8px;
    border:1px solid #ccc;
}
button{
    background:#5a55e6;
    color:white;
    border:none;
    cursor:pointer;
}
.bot{
    background:#eef3ff;
    padding:15px;
    margin-top:20px;
    border-radius:10px;
}

/* ABOUT */
.about{
    background:white;
    border-radius:20px;
    padding:40px;
    box-shadow:0 10px 25px rgba(0,0,0,.1);
    animation:fade 1s;
}

/* ANIMATION */
@keyframes fade{
    from{opacity:0}
    to{opacity:1}
}
@keyframes slide{
    from{transform:translateY(40px);opacity:0}
    to{transform:translateY(0);opacity:1}
}

/* RESPONSIVE */
@media(max-width:900px){
    .hero{flex-direction:column;text-align:center}
    .hero img{margin-top:40px}
    nav{padding:15px 20px}
}
/* Gejala Umum */
.gejala-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:30px;
    margin-top:40px;
}

.gejala-card{
    background:white;
    padding:30px 25px;
    border-radius:18px;
    box-shadow:0 15px 35px rgba(0,0,0,.08);
    transition:.3s;
    position:relative;
}

.gejala-card:hover{
    transform:translateY(-6px);
}

.gejala-icon{
    width:48px;
    height:48px;
    border-radius:50%;
    background:#eef3ff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:22px;
    margin-bottom:15px;
}

.gejala-card h4{
    margin:10px 0 8px;
    color:#5a55e6;
}

.gejala-card p{
    font-size:14px;
    color:#444;
    line-height:1.6;
}

.gejala-solusi{
    margin-top:15px;
    padding-top:12px;
    border-top:1px solid #eee;
    font-size:13px;
    color:#666;
}

</style>
</head>

<body>

<nav>
<b>Sistem Pakar Mata Anak</b>
<div>
<a href="#home">🏠Beranda</a>
<a href="#diagnosa">🩺Diagnosa</a>
<a href="#gejala">👁️Gejala Umum</a>
<a href="#tentang">ℹ️Tentang Sistem</a>
</div>
</nav>

<section id="home" class="hero">
<div class="hero-text">
<h1>Diagnosa Penyakit Mata Anak Akibat Penggunaan Gadget</h1>
<p>
Sistem pakar berbasis web yang memanfaatkan Natural Language Processing (NLP)
dengan algoritma Forward Chaining untuk membantu orang tua mendeteksi gangguan
penglihatan pada anak akibat penggunaan gadget berlebihan.
</p>
<a href="#diagnosa">Mulai Diagnosa →</a>
</div>
<img src="img/dokter-anak.png" alt="Dokter memeriksa mata anak">
</section>

<div class="chat">
<form method="post">
<input type="text" name="keluhan" placeholder="Contoh: mata anak perih dan penglihatan kabur" value="<?= isset($_POST['keluhan']) ? htmlspecialchars($_POST['keluhan']) : '' ?>">
<button type="submit">Mulai Diagnosa</button>
</form>

<?php if($error != ""): ?>
<div class="bot" style="background:#ffecec; color:#b00000; border-left:5px solid #ff4d4d;">
<b>Error:</b> <?= $error ?>
</div>
<?php endif; ?>

<?php if(!empty($hasil)): ?>
<?php
usort($hasil, function($a,$b){ return $b['nilai'] <=> $a['nilai']; });

$utama = $hasil[0];
$ragAI = generateRAGResponse($_POST['keluhan'], $utama['penyakit'], $conn);
?>

<div class="bot">

<h3>🧠 Hasil Diagnosa Sistem Pakar</h3>

<p>
Berdasarkan hasil analisis sistem pakar menggunakan metode <i>forward chaining</i>,
penyakit yang paling mungkin dialami oleh anak adalah
<b><?= $utama['penyakit'] ?></b>
dengan tingkat keyakinan sebesar
<b><?= $utama['nilai'] ?>%</b>.
Penyakit ini ditentukan berdasarkan kecocokan antara gejala yang diinputkan pengguna
dengan basis aturan yang tersimpan di dalam sistem.
</p>

<p>
<b>Solusi dan Penanganan:</b><br>
<?= $utama['solusi'] ?>
</p>

<hr>

<p>
<b>Kemungkinan Penyakit Lain:</b><br>
<?php
$paragrafLain = [];
foreach($hasil as $i => $h){
    if($i == 0) continue;
    $paragrafLain[] = $h['penyakit']." dengan tingkat keyakinan sebesar ".$h['nilai']."%";
}

if(!empty($paragrafLain)){
    echo "Selain diagnosa utama, sistem juga mengidentifikasi kemungkinan penyakit lain yang dapat terjadi, yaitu "
        .implode(", ", $paragrafLain).
        ". Penyakit-penyakit tersebut memiliki kemiripan gejala dengan diagnosa utama sehingga masih berpotensi dialami meskipun tingkat keyakinannya lebih rendah.";
}else{
    echo "Sistem tidak menemukan kemungkinan penyakit lain berdasarkan gejala yang diberikan.";
}
?>
</p>

<hr>

<p>
<b>🤖 Penjelasan AI (RAG OpenAI)</b><br>
<?= nl2br($ragAI) ?>
</p>

<p style="color:#b00000;font-size:13px">
⚠ Hasil diagnosa ini bersifat awal dan tidak dapat menggantikan pemeriksaan medis oleh dokter spesialis mata.
</p>

</div>
<?php endif; ?>


</div>

</body>
</html>

<section id="gejala">
<h2>Gejala Umum Penyakit Mata Pada Anak</h2>
<div class="gejala-grid">

    <div class="gejala-card">
        <div class="gejala-icon">👁️</div>
        <h4>Mata Cepat Lelah</h4>
        <p>Anak sering mengeluh mata terasa lelah setelah menatap layar dalam waktu lama.</p>
        <div class="gejala-solusi">
            Istirahatkan mata setiap 20 menit (aturan 20-20-20).
        </div>
    </div>

    <div class="gejala-card">
        <div class="gejala-icon">🔍</div>
        <h4>Penglihatan Kabur</h4>
        <p>Objek terlihat tidak jelas setelah penggunaan gadget.</p>
        <div class="gejala-solusi">.
            Kurangi durasi gadget dan periksa mata ke dokter.
        </div>
    </div>

    <div class="gejala-card">
        <div class="gejala-icon">💧</div>
        <h4>Mata Kering</h4>
        <p>Mata terasa kering, perih, atau panas.</p>
        <div class="gejala-solusi">
            Perbanyak berkedip dan istirahatkan mata.
        </div>
    </div>

    <div class="gejala-card">
        <div class="gejala-icon">🤕</div>
        <h4>Sering Pusing</h4>
        <p>Pusing atau sakit kepala setelah melihat layar.</p>
        <div class="gejala-solusi">
            Atur pencahayaan agar lebih rendah dan atur jarak pandang.
        </div>
    </div>

    <div class="gejala-card">
        <div class="gejala-icon">📏</div>
        <h4>Sulit Melihat Jarak Jauh</h4>
        <p>Anak kesulitan melihat objek dari kejauhan.</p>
        <div class="gejala-solusi">
            Lakukan pemeriksaan mata ke dokter.
        </div>
    </div>

</div>
</section>

<section id="tentang">
<h2>Tentang Sistem</h2>
<div class="about">
<p>Sistem ini merupakan sistem pakar diagnosa penyakit mata pada anak berbasis web. Memanfaatkan NLP untuk memproses keluhan dan algoritma Forward Chaining sebagai mesin inferensi untuk menentukan diagnosis.</p>
<p>Basis pengetahuan disusun berdasarkan data gejala, penyakit, dan aturan IF–THEN yang merepresentasikan pengetahuan pakar. Sistem ini membantu deteksi dini gangguan penglihatan akibat gadget.</p>
<hr>
<p><b>👨‍💻 Pengembang Sistem:</b></p>
<ul>
<li>Nama: <b>Ricardo Sapakoly</b></li>
<li>Program Studi: Teknik Informatika</li>
</ul>
<p><b>📞 Kontak</b><br><a href="https://wa.me/6281244964493" target="_blank">💬 Hubungi via WhatsApp</a></p>
</div>
</section>

</body>
</html>
