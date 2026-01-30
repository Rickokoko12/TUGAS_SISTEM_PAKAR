<?php
require_once "config_openai.php";

function generateRAGResponse($keluhan, $penyakit_awal, $conn) {

    // Ambil knowledge base penyakit
    $kb_text = "";
    $stmt = $conn->prepare("
        SELECT kb.penjelasan, kb.pencegahan
        FROM knowledge_base kb
        JOIN penyakit p ON kb.id_penyakit = p.id_penyakit
        WHERE p.nama_penyakit = ?
    ");
    $stmt->bind_param("s", $penyakit_awal);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        $kb_text = "Penjelasan: ".$res['penjelasan']."\nPencegahan: ".$res['pencegahan'];
    }

    $prompt = "
Anda adalah asisten medis berbasis akademik.

Keluhan pengguna:
{$keluhan}

Diagnosa awal:
{$penyakit_awal}

Basis pengetahuan:
{$kb_text}

Instruksi:
1. Jelaskan diagnosa awal dengan bahasa akademik dan mudah dipahami.
2. Tambahkan kalimat: 'Penyakit ini juga bisa dikaitkan dengan gejala lain jika relevan.'
3. Jangan menambahkan penyakit baru di luar data.
4. Tegaskan bahwa ini bukan diagnosis medis final.
";

    $data = [
        "model" => "gpt-4.1-mini",
        "messages" => [
            ["role" => "system", "content" => "Anda adalah asisten medis akademik."],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.3
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer ".OPENAI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['choices'][0]['message']['content'] ?? "Penjelasan AI tidak tersedia.";
}
