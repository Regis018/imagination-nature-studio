
<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Photo Editing Order';
$unitPrice = 3000;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Imagination Nature Studio</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f3ef;
            color: #211d19;
        }
        .wrap {
            width: min(620px, calc(100% - 28px));
            margin: 35px auto;
        }
        .brand {
            text-align: center;
            margin-bottom: 20px;
        }
        .brand h1 { margin: 0 0 8px; font-size: 25px; }
        .brand p { color: #68615b; margin: 0; }
        .card {
            background: #fff;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,.07);
        }
        label {
            display: block;
            font-weight: 700;
            margin: 17px 0 7px;
        }
        input, textarea {
            display: block;
            width: 100%;
            padding: 13px;
            border: 1px solid #d5d0ca;
            border-radius: 9px;
            font: inherit;
            background: #fff;
        }
        textarea { min-height: 95px; resize: vertical; }
        .hint { color: #6b655f; font-size: 13px; margin-top: 6px; }
        .total {
            margin-top: 20px;
            padding: 18px;
            background: #f5f3ef;
            border-radius: 10px;
        }
        .total strong { font-size: 25px; display: block; margin-top: 5px; }
        button {
            width: 100%;
            border: 0;
            border-radius: 9px;
            padding: 15px;
            margin-top: 20px;
            background: #171513;
            color: white;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }
        button:hover { background: #34302b; }
        .footer { text-align: center; color: #746c65; font-size: 12px; margin-top: 18px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <h1>Imagination Nature Studio</h1>
        <p>Photo Editing Order</p>
    </div>

    <div class="card">
        <h2>Choose Your Photos</h2>
        <p>Enter your details and the number of photos you want edited.</p>

        <form method="post"
              action="<?= e(SITE_URL) ?>/process-photo-order.php">
            <?= csrf_field() ?>

            <label for="customer_name">Full name *</label>
            <input id="customer_name" name="customer_name"
                   type="text" maxlength="150" required
                   autocomplete="name">

            <label for="customer_phone">Phone / WhatsApp number *</label>
            <input id="customer_phone" name="customer_phone"
                   type="tel" maxlength="40" required
                   autocomplete="tel"
                   placeholder="e.g. 078xxxxxxx">

            <label for="photo_quantity">Number of photos *</label>
            <input id="photo_quantity" name="photo_quantity"
                   type="number" min="1" max="10000"
                   step="1" value="1" required>
            <div class="hint">Price per photo: 3,000 RWF</div>

            <div class="total" aria-live="polite">
                Estimated total
                <strong id="totalAmount">3,000 RWF</strong>
                <div class="hint">Final amount is recalculated securely when you submit.</div>
            </div>

            <label for="feedback">Feedback or special instructions (optional)</label>
            <textarea id="feedback" name="feedback" maxlength="2000"
                      placeholder="Write a note if you wish."></textarea>

            <button type="submit">Submit Photo Order</button>
        </form>
    </div>

    <div class="footer">
        Imagination Nature Studio · Kigali, Rwanda
    </div>
</div>

<script>
(function () {
    const quantity = document.getElementById('photo_quantity');
    const total = document.getElementById('totalAmount');
    const price = 3000;

    function updateTotal() {
        const q = Number.parseInt(quantity.value, 10);
        total.textContent = Number.isInteger(q) && q >= 1 && q <= 10000
            ? (q * price).toLocaleString('en-US') + ' RWF'
            : 'Enter a valid quantity';
    }

    quantity.addEventListener('input', updateTotal);
    updateTotal();
})();
</script>
</body>
</html>
