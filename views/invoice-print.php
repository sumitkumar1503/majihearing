<?php
$inv = entity_find('invoices', $_GET['id'] ?? '');
if (!$inv) { http_response_code(404); echo 'Invoice not found'; return; }

function num_to_words($num) {
    $num = (float)$num;
    if ($num == 0) return 'Zero';
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $convert = function($n) use (&$convert, $ones, $tens) {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return $tens[intval($n/10)] . ($n%10 ? ' ' . $ones[$n%10] : '');
        if ($n < 1000) return $ones[intval($n/100)] . ' Hundred' . ($n%100 ? ' and ' . $convert($n%100) : '');
        if ($n < 100000) return $convert(intval($n/1000)) . ' Thousand' . ($n%1000 ? ' ' . $convert($n%1000) : '');
        if ($n < 10000000) return $convert(intval($n/100000)) . ' Lakh' . ($n%100000 ? ' ' . $convert($n%100000) : '');
        return $convert(intval($n/10000000)) . ' Crore' . ($n%10000000 ? ' ' . $convert($n%10000000) : '');
    };
    $whole = intval($num);
    return $convert($whole) . ' Rupees';
}

$items = $inv['items'];
$dateStr = format_date($inv['date'], 'd/m/Y');
$totalWords = num_to_words($inv['total']);
$itemRows = '';
foreach ($items as $i => $item) {
    $itemRows .= '<tr><td style="text-align:center;border:1px solid #333;padding:6px 8px;">' . ($i+1) . '</td><td style="border:1px solid #333;padding:6px 8px;">' . h($item['description'] ?: ($item['item'] ?? '')) . '</td><td style="text-align:center;border:1px solid #333;padding:6px 8px;">' . h($item['quantity']) . '</td><td style="text-align:right;border:1px solid #333;padding:6px 8px;">₹' . number_format((float)$item['price']) . '</td><td style="text-align:right;border:1px solid #333;padding:6px 8px;">₹' . number_format((float)$item['total']) . '</td></tr>';
}
$emptyRows = '';
for ($k = 0; $k < max(0, 6 - count($items)); $k++) {
    $emptyRows .= '<tr><td style="text-align:center;border:1px solid #333;padding:6px 8px;">&nbsp;</td><td style="border:1px solid #333;padding:6px 8px;">&nbsp;</td><td style="text-align:center;border:1px solid #333;padding:6px 8px;">&nbsp;</td><td style="text-align:right;border:1px solid #333;padding:6px 8px;">&nbsp;</td><td style="text-align:right;border:1px solid #333;padding:6px 8px;">&nbsp;</td></tr>';
}
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Invoice <?= h($inv['invoiceNo']) ?></title>
<style>
  @page { size: A4; margin: 0; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; width: 210mm; min-height: 297mm; margin: 0 auto; background: #fff; color: #1a1a1a; position: relative; font-size: 11px; line-height: 1.4; }
  .page-container { position: relative; width: 100%; min-height: 297mm; overflow: hidden; }
  .header-wave { position: relative; width: 100%; height: 185px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%); overflow: visible; }
  .header-wave::after { content: ''; position: absolute; bottom: -30px; left: 0; width: 100%; height: 60px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%); clip-path: ellipse(55% 100% at 50% 0%); }
  .header-content { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: flex-start; padding: 18px 35px 10px; color: #fff; }
  .clinic-name { font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
  .clinic-tagline { font-size: 11px; font-style: italic; color: #ccc; margin-top: 2px; letter-spacing: 1px; }
  .clinic-logo-icon { width: 118px; height: 118px; border-radius: 50%; overflow: hidden; margin-right: 18px; flex-shrink: 0; }
  .clinic-logo-icon img { width: 100%; height: 100%; object-fit: cover; }
  .header-left { display: flex; align-items: center; }
  .header-right { text-align: right; font-size: 10px; line-height: 1.6; max-width: 260px; }
  .header-right .branch-name { font-size: 13px; font-weight: 700; margin-bottom: 2px; }
  .body-content { padding: 50px 35px 20px; }
  .invoice-title { text-align: center; font-size: 22px; font-weight: 700; letter-spacing: 3px; margin-bottom: 8px; color: #CC0000; }
  .invoice-meta { display: flex; justify-content: space-between; margin-bottom: 18px; font-size: 12px; font-weight: 600; }
  .patient-info { margin-bottom: 16px; }
  .patient-row { display: flex; align-items: baseline; margin-bottom: 6px; font-size: 12px; }
  .patient-label { font-weight: 700; min-width: 110px; flex-shrink: 0; }
  .patient-value { margin-left: 6px; padding-bottom: 2px; }
  .patient-dots { flex: 1; border-bottom: 1px dotted #666; margin-left: 6px; padding-bottom: 2px; }
  .items-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  .items-table th { background: #CC0000; color: #fff; font-weight: 700; font-size: 11px; padding: 8px; border: 1px solid #333; text-transform: uppercase; letter-spacing: 0.5px; }
  .items-table td { font-size: 11px; }
  .totals-section { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; padding-top: 4px; }
  .rupees-words { font-size: 11px; flex: 1; margin-right: 20px; }
  .grand-total-box { text-align: right; min-width: 200px; }
  .grand-total-label { font-size: 13px; font-weight: 700; }
  .grand-total-amount { font-size: 16px; font-weight: 700; color: #CC0000; }
  .discount-line { display: flex; justify-content: flex-end; margin-bottom: 4px; font-size: 11px; gap: 10px; }
  .instructions { margin-top: 14px; border-top: 2px solid #333; padding-top: 10px; }
  .instructions-title { font-size: 11px; font-weight: 700; text-decoration: underline; text-align: center; margin-bottom: 8px; letter-spacing: 0.5px; }
  .instructions ol { padding-left: 18px; font-size: 9px; line-height: 1.5; }
  .instructions li { margin-bottom: 2px; }
  .battery-note { font-weight: 700; font-size: 8.5px; color: #CC0000; padding-left: 14px; margin: 1px 0; }
  .footer-section { margin-top: 20px; display: flex; justify-content: space-between; align-items: flex-end; }
  .signature-block { font-size: 11px; font-weight: 600; text-align: center; min-width: 180px; }
  .signature-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; }
  .footer-wave { position: relative; width: 100%; height: 60px; margin-top: 15px; }
  .footer-wave-shape { position: absolute; bottom: 0; left: 0; width: 100%; height: 60px; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%); clip-path: ellipse(55% 100% at 50% 100%); }
  @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .no-print { display: none !important; } }
  .btn-bar { display: flex; justify-content: center; gap: 12px; padding: 15px 0; }
  .action-btn { border: none; padding: 10px 30px; font-size: 14px; font-weight: 600; border-radius: 8px; cursor: pointer; }
  .btn-print { background: #CC0000; color: #fff; } .btn-download { background: #4f46e5; color: #fff; }
</style></head>
<body>
<div class="page-container">
  <div class="header-wave"><div class="header-content">
    <div class="header-left"><div class="clinic-logo-icon"><img src="../assets/logo.jpg" alt="Logo" onerror="this.style.display='none'"></div><div><div class="clinic-name">Maji Hearing Aids Centre</div><div class="clinic-tagline">Your Hearing, Our Priority</div></div></div>
    <div class="header-right">
      <div class="branch-name">Serampore Branch</div>
      51, Dr. G. C. Goswami Street, Near Railway Station (Goswami Para).<br>
      <div class="branch-name" style="margin-top:5px;">Konnagar Branch</div>
      19A, S K Deb Street, Konnagar<br>
      <div style="margin-top:5px;">Call us at: 8859997977 / 9831493073</div>
      WhatsApp us at: 7439663366<br>
      Email us at: majiheaingaidscentre@gmail.com
    </div>
  </div></div>
  <div class="body-content">
    <div class="invoice-title">INVOICE</div>
    <div class="invoice-meta"><span>No. <?= h($inv['invoiceNo']) ?></span><span>Date: <?= h($dateStr) ?></span></div>
    <div class="patient-info">
      <div class="patient-row"><span class="patient-label">Patient's Name:</span><span class="patient-value"><?= h($inv['patientName']) ?></span><span class="patient-dots"></span></div>
      <div class="patient-row"><span class="patient-label">Address:</span><span class="patient-value"><?= h($inv['address']) ?></span><span class="patient-dots"></span></div>
      <div class="patient-row"><span class="patient-label">Phone No.:</span><span class="patient-value"><?= h($inv['phoneNumber']) ?></span><span class="patient-dots"></span></div>
    </div>
    <table class="items-table"><thead><tr><th style="width:8%;text-align:center;">Sl. No.</th><th style="width:42%;">Item Description</th><th style="width:10%;text-align:center;">Qnty.</th><th style="width:18%;text-align:right;">Unit Price</th><th style="width:22%;text-align:right;">Total Amount</th></tr></thead><tbody><?= $itemRows . $emptyRows ?></tbody></table>
    <div class="discount-line"><span>Subtotal:</span><span>₹<?= number_format((float)$inv['subtotal']) ?></span></div>
    <?php if ((float)$inv['discount']): ?><div class="discount-line"><span>Discount:</span><span>₹<?= number_format((float)$inv['discount']) ?></span></div><?php endif; ?>
    <div class="totals-section">
      <div class="rupees-words"><strong>Rupees in Word:</strong> <?= h($totalWords) ?> only.</div>
      <div class="grand-total-box"><span class="grand-total-label">Grand Total: </span><span class="grand-total-amount">₹<?= number_format((float)$inv['total']) ?></span></div>
    </div>
    <div class="instructions"><div class="instructions-title">INSTRUCTIONS AND CONSENT FORM FOR PATIENT(S)</div>
      <ol>
        <li>Minimum 8 to 10 hours of Hearing Aids usage per day is necessary.</li>
        <li>(a) No. P10 Battery – 3 to 5 days (Approx.) | Don't store old batteries.<br>(b) No. P312 Battery – 5 to 10 days (Approx.) | Change battery on time.<br>(c) No. P13 Battery – 7 to 13 days (Approx.) | Don't ignore low battery indication.<br>(d) No. P675 Battery – 12 to 18 days (Approx.) | Positive side of the battery stays on top.<div class="battery-note"># Battery life depends on the Hearing Loss, Use of Bluetooth & the Model of the Hearing Aid.</div></li>
        <li>Hearing Aid(s) is not Waterproof. Liquid Damage OR Battery Leakage does not fall under the warranty period.</li>
        <li>Do not drop the Hearing Aid(s) on a hard surface. Any physical damage does not fall under warranty.</li>
        <li>For RIC models, receiver does not fall under warranty.</li>
        <li>Audiometry Test should be done at least Once a Year.</li>
        <li>Avail Free Fine Tuning & Programming of hearing aid(s) for the life of the hearing aid(s) at clinic, against prior appointment.</li>
        <li>Open the Battery door when the Hearing Aid(s) are not in use. Keep them with the battery door open, in the De-humidifier Box every night.</li>
        <li>De-humidifier Box needs to be kept under direct sunlight for at least 6 hours, once or twice a month.</li>
        <li>Binaural (Both Ear) usage is the BEST Practice.</li>
        <li>Clean the Hearing aid(s) every night before sleep. (Very Important)</li>
        <li>Do Not wear Hearing aid(s) during Ear Discharge/Infection/Pain if any. Consult an ENT doctor immediately during these symptoms.</li>
        <li>Wear the Hearing Aid(s) after identifying the colour marking (Red stands for Right ear & Blue Stands for Left Ear).</li>
        <li><strong>Hearing Aid(s) once sold can't be RETURNED.</strong></li>
      </ol>
    </div>
    <div class="footer-section"><div class="signature-block"><div class="signature-line">Patient's Signature</div></div><div class="signature-block"><div class="signature-line">Company Stamp & Signature</div></div></div>
  </div>
  <div class="footer-wave"><div class="footer-wave-shape"></div></div>
</div>
<div class="btn-bar no-print"><button class="action-btn btn-print" onclick="window.print()">🖨️ Print</button></div>
</body></html>
<?php return; ?>
