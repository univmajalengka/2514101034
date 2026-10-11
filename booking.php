<?php
require __DIR__ . '/packages.php';

$selectedPackage = $_GET['paket'] ?? '';
$customerName = '';
$customerPhone = '';
$bookingDate = '';
$experience = 'Pemula / belum pernah mendaki';
$participantNames = [''];
$porterNeeded = false;
$porterCount = 1;
$notes = '';
$errors = [];
$notice = '';
$whatsAppUrl = '';

if (!is_string($selectedPackage) || !array_key_exists($selectedPackage, $packages)) {
    $selectedPackage = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedPackage = $_POST['package'] ?? '';
    $customerName = trim(is_string($_POST['customer_name'] ?? null) ? $_POST['customer_name'] : '');
    $customerPhone = trim(is_string($_POST['customer_phone'] ?? null) ? $_POST['customer_phone'] : '');
    $bookingDate = trim(is_string($_POST['date'] ?? null) ? $_POST['date'] : '');
    $experienceOptions = ['Pemula / belum pernah mendaki', 'Pernah mendaki 1-2 kali', 'Sudah berpengalaman'];
    $experience = $_POST['experience'] ?? $experienceOptions[0];
    if (!is_string($experience) || !in_array($experience, $experienceOptions, true)) {
        $experience = $experienceOptions[0];
    }
    $postedNames = $_POST['participants'] ?? [''];
    $participantNames = is_array($postedNames)
        ? array_slice(array_map(static fn ($name) => is_string($name) ? trim($name) : '', $postedNames), 0, 15)
        : [''];
    if ($participantNames === []) {
        $participantNames = [''];
    }
    $porterNeeded = isset($_POST['porter_needed']);
    $postedPorterCount = filter_var($_POST['porter_count'] ?? 1, FILTER_VALIDATE_INT);
    $porterCount = $postedPorterCount === false ? 1 : max(1, min(5, $postedPorterCount));
    $notes = trim(is_string($_POST['notes'] ?? null) ? $_POST['notes'] : '');
    $action = $_POST['action'] ?? 'book';

    if (!is_string($selectedPackage) || !array_key_exists($selectedPackage, $packages)) {
        $selectedPackage = '';
    }

    if ($action === 'add_participant' && count($participantNames) < 15) {
        $participantNames[] = '';
        $notice = 'Kolom peserta ditambahkan. Isi nama semua peserta sebelum mengirim permintaan.';
    } elseif (is_string($action) && preg_match('/^remove_participant:(\d+)$/', $action, $matches)) {
        $removeIndex = (int) $matches[1];
        if (count($participantNames) > 1 && isset($participantNames[$removeIndex])) {
            array_splice($participantNames, $removeIndex, 1);
        }
        $notice = 'Daftar peserta diperbarui.';
    } elseif ($action === 'update_estimate') {
        $notice = 'Estimasi diperbarui berdasarkan pilihan saat ini.';
    } else {
        if ($selectedPackage === '') {
            $errors[] = 'Pilih paket pendakian terlebih dahulu.';
        }
        if ($customerName === '') {
            $errors[] = 'Nama pemesan wajib diisi.';
        }
        if (!preg_match('/^\+?[0-9 ()-]{8,20}$/', $customerPhone)) {
            $errors[] = 'Nomor WhatsApp pemesan tidak valid.';
        }

        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $bookingDate);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $bookingDate || $parsedDate < new DateTimeImmutable('today')) {
            $errors[] = 'Pilih tanggal mulai yang valid dan belum lewat.';
        } elseif ($selectedPackage !== '' && $packages[$selectedPackage]['type'] === 'PUBLIC' && !in_array((int) $parsedDate->format('N'), [6, 7], true)) {
            $errors[] = 'Paket public hanya menerima pengajuan hari Sabtu atau Minggu.';
        }

        foreach ($participantNames as $index => $participantName) {
            if ($participantName === '') {
                $errors[] = 'Nama peserta ' . ($index + 1) . ' wajib diisi.';
            }
        }

        if ($porterNeeded && ($postedPorterCount === false || $postedPorterCount < 1 || $postedPorterCount > 5)) {
            $errors[] = 'Jumlah porter harus antara 1 sampai 5 orang.';
        }

        if ($errors === []) {
            $package = $packages[$selectedPackage];
            $dateLabel = $parsedDate->format('d-m-Y');
            $packageTotal = $package['pricing'] === 'per_person'
                ? $package['price'] * count($participantNames)
                : $package['price'] + max(0, count($participantNames) - $package['included_people']) * $package['extra_person_price'];
            $porterTotal = $porterNeeded ? $porterPricePerDay * $porterCount * $package['days'] : 0;
            $participantList = [];
            foreach ($participantNames as $index => $participantName) {
                $participantList[] = ($index + 1) . '. ' . $participantName;
            }
            $message = [
                'Halo Basecamp Tampomas, saya ingin mengajukan booking open trip/guide.',
                'Nama pemesan: ' . $customerName,
                'Nomor WhatsApp pemesan: ' . $customerPhone,
                'Paket: ' . $package['name'] . ' (' . $package['duration'] . ')',
                'Tanggal mulai: ' . $dateLabel,
                'Daftar peserta (' . count($participantNames) . ' orang):',
                implode("\n", $participantList),
                'Pengalaman: ' . $experience,
                'Porter: ' . ($porterNeeded ? $porterCount . ' orang' : 'Tidak'),
                'Catatan: ' . ($notes !== '' ? $notes : '-'),
                'Estimasi dari website: Rp ' . number_format($packageTotal + $porterTotal, 0, ',', '.') . ' (harga simulasi, mohon konfirmasi).',
                'Mohon konfirmasi ketersediaan guide, jadwal, fasilitas, dan harga final. Saya memahami permintaan ini belum menjadi booking.'
            ];
            $whatsAppUrl = 'https://wa.me/' . $basecampPhone . '?text=' . rawurlencode(implode("\n", $message));
        }
    }
}

$selectedPackageData = $selectedPackage !== '' ? $packages[$selectedPackage] : null;
$packageTotal = 0;
$porterTotal = 0;
if ($selectedPackageData !== null) {
    $packageTotal = $selectedPackageData['pricing'] === 'per_person'
        ? $selectedPackageData['price'] * count($participantNames)
        : $selectedPackageData['price'] + max(0, count($participantNames) - $selectedPackageData['included_people']) * $selectedPackageData['extra_person_price'];
    $porterTotal = $porterNeeded ? $porterPricePerDay * $porterCount * $selectedPackageData['days'] : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Pilih paket, isi data peserta, dan ajukan booking guide Gunung Tampomas.">
    <title>Booking Guide Tampomas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="booking-page">
    <header class="booking-header" id="booking-top">
        <a class="brand" href="index.php">TAMPOMAS <span>/ OPEN TRIP</span></a>
        <a class="back-link" href="index.php#paket">← Kembali ke paket</a>
    </header>

    <main class="booking-main container">
        <div class="booking-title">
            <p class="eyebrow dark">Pemesanan guide Tampomas</p>
            <h1>Rencanakan pendakianmu.</h1>
            <p>Isi data peserta dan kebutuhan porter. Permintaan akan dikirim ke basecamp untuk konfirmasi jadwal dan ketersediaan.</p>
        </div>

        <?php if ($notice !== ''): ?>
            <div class="alert alert-success" role="status"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert">
                <strong>Periksa data berikut:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="order-layout" id="booking-form" method="post" action="booking.php#booking-form">
            <div class="order-fields">
                <section class="form-section">
                    <div class="form-section-heading"><span>01</span><h2>Pilih paket & tanggal</h2></div>
                    <div class="form-field mb-3">
                        <label for="package-id">Paket pendakian</label>
                        <select class="form-select" id="package-id" name="package" required>
                            <option value="">Pilih paket</option>
                            <?php foreach ($packages as $package): ?>
                                <option value="<?php echo htmlspecialchars($package['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedPackage === $package['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($package['name'] . ' · ' . $package['duration'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field mb-3">
                        <label for="start-date">Tanggal mulai</label>
                        <input class="form-control" id="start-date" name="date" type="date" min="<?php echo (new DateTimeImmutable('today'))->format('Y-m-d'); ?>" value="<?php echo htmlspecialchars($bookingDate, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <span class="field-hint">
                            <?php if ($selectedPackageData !== null): ?>
                                Jadwal pengajuan: <?php echo htmlspecialchars($selectedPackageData['schedule'], ENT_QUOTES, 'UTF-8'); ?>. Keberangkatan menunggu konfirmasi.
                            <?php else: ?>
                                Pilih paket untuk melihat informasi jadwal.
                            <?php endif; ?>
                        </span>
                    </div>
                </section>

                <section class="form-section">
                    <div class="form-section-heading"><span>02</span><h2>Data pemesan</h2></div>
                    <div class="form-row">
                        <div class="form-field mb-3">
                            <label for="customer-name">Nama pemesan</label>
                            <input class="form-control" id="customer-name" name="customer_name" type="text" autocomplete="name" placeholder="Nama lengkap" value="<?php echo htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="form-field mb-3">
                            <label for="customer-phone">Nomor WhatsApp pemesan</label>
                            <input class="form-control" id="customer-phone" name="customer_phone" type="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" value="<?php echo htmlspecialchars($customerPhone, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>
                    <div class="form-field mb-3">
                        <label for="experience">Pengalaman mendaki</label>
                        <select class="form-select" id="experience" name="experience" required>
                            <option value="Pemula / belum pernah mendaki" <?php echo $experience === 'Pemula / belum pernah mendaki' ? 'selected' : ''; ?>>Pemula / belum pernah mendaki</option>
                            <option value="Pernah mendaki 1-2 kali" <?php echo $experience === 'Pernah mendaki 1-2 kali' ? 'selected' : ''; ?>>Pernah mendaki 1-2 kali</option>
                            <option value="Sudah berpengalaman" <?php echo $experience === 'Sudah berpengalaman' ? 'selected' : ''; ?>>Sudah berpengalaman</option>
                        </select>
                    </div>
                </section>

                <section class="form-section">
                    <div class="form-section-heading"><span>03</span><h2>Nama semua peserta</h2></div>
                    <p class="section-help">Masukkan nama setiap orang yang ikut, termasuk pemesan.</p>
                    <div class="participant-list">
                        <?php foreach ($participantNames as $index => $participantName): ?>
                            <div class="participant-row">
                                <label for="participant-<?php echo $index + 1; ?>">Peserta <?php echo $index + 1; ?></label>
                                <input class="form-control" id="participant-<?php echo $index + 1; ?>" name="participants[]" type="text" placeholder="Nama lengkap sesuai identitas" value="<?php echo htmlspecialchars($participantName, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <button class="remove-participant btn btn-sm btn-outline-danger" type="submit" name="action" value="remove_participant:<?php echo $index; ?>" formnovalidate <?php echo count($participantNames) === 1 ? 'disabled' : ''; ?>>Hapus</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="add-participant btn btn-outline-success" type="submit" name="action" value="add_participant" formnovalidate <?php echo count($participantNames) >= 15 ? 'disabled' : ''; ?>>+ Tambah nama peserta</button>
                </section>

                <section class="form-section">
                    <div class="form-section-heading"><span>04</span><h2>Perlengkapan tambahan</h2></div>
                    <label class="porter-toggle" for="porter-needed">
                        <input id="porter-needed" name="porter_needed" type="checkbox" value="1" <?php echo $porterNeeded ? 'checked' : ''; ?>>
                        <span><strong>Tambah jasa porter</strong><small>Biaya dihitung per porter per hari.</small></span>
                    </label>
                    <div class="form-field porter-count-field">
                        <label for="porter-count">Jumlah porter <span>(jika dipilih)</span></label>
                        <input class="form-control" id="porter-count" name="porter_count" type="number" min="1" max="5" value="<?php echo $porterCount; ?>">
                    </div>
                    <div class="form-field">
                        <label for="special-notes">Catatan untuk guide <span>(opsional)</span></label>
                        <textarea class="form-control" id="special-notes" name="notes" rows="3" placeholder="Alergi, kebutuhan khusus, atau pertanyaan paket"><?php echo htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </section>

                <button class="booking-submit btn btn-outline-success" type="submit" name="action" value="update_estimate" formnovalidate>Perbarui estimasi</button>
            </div>

            <aside class="order-summary">
                <p class="summary-kicker">RINGKASAN PERMINTAAN</p>
                <h2><?php echo $selectedPackageData !== null ? htmlspecialchars($selectedPackageData['name'], ENT_QUOTES, 'UTF-8') : 'Pilih paket pendakian'; ?></h2>
                <p class="summary-duration">
                    <?php if ($selectedPackageData !== null): ?>
                        <?php echo htmlspecialchars($selectedPackageData['duration'] . ' · ' . $selectedPackageData['schedule'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php else: ?>
                        Durasi dan jadwal akan tampil setelah estimasi diperbarui.
                    <?php endif; ?>
                </p>
                <div class="summary-line"><span>Paket</span><strong>Rp <?php echo number_format($packageTotal, 0, ',', '.'); ?></strong></div>
                <div class="summary-line"><span>Porter</span><strong>Rp <?php echo number_format($porterTotal, 0, ',', '.'); ?></strong></div>
                <div class="summary-total"><span>Estimasi total</span><strong>Rp <?php echo number_format($packageTotal + $porterTotal, 0, ',', '.'); ?></strong></div>
                <p class="summary-note">Harga pada halaman ini adalah simulasi awal. Total final, fasilitas, izin, dan ketersediaan guide dikonfirmasi oleh basecamp.</p>
                <?php if ($whatsAppUrl !== ''): ?>
                    <a class="booking-submit btn btn-success" href="<?php echo htmlspecialchars($whatsAppUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Lanjutkan ke WhatsApp <span aria-hidden="true">↗</span></a>
                    <p class="form-status">Permintaan sudah disiapkan. Booking dan harga final menunggu konfirmasi basecamp.</p>
                <?php else: ?>
                    <button class="booking-submit btn btn-success" type="submit" name="action" value="book">Kirim permintaan booking <span aria-hidden="true">↗</span></button>
                    <p class="form-status">Belum ada pembayaran online. Slot belum dipesan sampai basecamp mengonfirmasi.</p>
                <?php endif; ?>
                <a class="summary-contact" href="tel:+<?php echo htmlspecialchars($basecampPhone, ENT_QUOTES, 'UTF-8'); ?>">Telepon basecamp: <?php echo htmlspecialchars($basecampPhoneDisplay, ENT_QUOTES, 'UTF-8'); ?></a>
            </aside>
        </form>
    </main>

    <footer class="booking-footer">
        <span>TAMPOMAS / OPEN TRIP</span>
        <a href="<?php echo htmlspecialchars($basecampMapUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Basecamp via Cibeureum · Google Maps ↗</a>
    </footer>

    <a class="back-to-top" href="#booking-top" aria-label="Kembali ke atas" title="Kembali ke atas">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
        </svg>
    </a>

</body>
</html>