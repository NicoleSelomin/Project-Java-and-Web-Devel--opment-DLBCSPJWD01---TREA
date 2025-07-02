<?php
// -----------------------------------------------------------------------------
// submit-inspection-report.php (TREA)
// Field Agent: Submit Initial/Final Inspection Report (État des lieux)
// -----------------------------------------------------------------------------
session_start();
require 'db_connect.php';

// Auth: Only field agents allowed
if (!isset($_SESSION['staff_id']) || strtolower($_SESSION['role']) !== 'field agent') {
    header("Location: staff-login.php");
    exit();
}
$agent_id = $_SESSION['staff_id'];

// Parameters
$claim_id = (int)($_GET['claim_id'] ?? 0);
$type     = ($_GET['type'] === 'final') ? 'final' : 'initial';

// Load property, client, and owner info
$stmt = $pdo->prepare("
    SELECT cc.*, p.property_name, p.location, c.client_id, u_c.full_name AS client_name,
           o.owner_id, u_o.full_name AS owner_name
    FROM client_claims cc
    JOIN properties p    ON cc.property_id = p.property_id
    JOIN clients c       ON cc.client_id = c.client_id
    JOIN users u_c       ON c.user_id = u_c.user_id
    JOIN owners o        ON p.owner_id = o.owner_id
    JOIN users u_o       ON o.user_id = u_o.user_id
    WHERE cc.claim_id = ?
");
$stmt->execute([$claim_id]);
$claim = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$claim) die("Claim not found");

// For final inspection, load initial report items
$items = [];
if ($type === 'final') {
    $report_init = $pdo->prepare("
        SELECT * FROM inspection_reports
        WHERE claim_id=? AND inspection_type='initial'
        ORDER BY submitted_at DESC LIMIT 1
    ");
    $report_init->execute([$claim_id]);
    $initialReport = $report_init->fetch(PDO::FETCH_ASSOC);
    if ($initialReport) {
        $item_stmt = $pdo->prepare("
            SELECT * FROM inspection_report_items
            WHERE report_id=? ORDER BY order_no ASC, item_id ASC
        ");
        $item_stmt->execute([$initialReport['report_id']]);
        $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        die("No initial inspection found! Please submit the initial inspection first.");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= ucfirst($type) ?> Inspection Report | TREA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<?php include 'header.php'; ?>

<main class="flex-grow-1">
    <div class="container py-4">
        <h3><?= ucfirst($type) ?> Inspection Report (État des lieux)</h3>
        <div class="mb-2">
            <strong>Property:</strong> <?= htmlspecialchars($claim['property_name']) ?> <br>
            <strong>Location:</strong> <?= htmlspecialchars($claim['location']) ?> <br>
            <strong>Client:</strong> <?= htmlspecialchars($claim['client_name']) ?> <br>
            <strong>Owner:</strong> <?= htmlspecialchars($claim['owner_name']) ?>
        </div>

        <form id="etatDesLieuxForm" method="POST" action="submit-inspection-report-handler.php">
            <input type="hidden" name="claim_id" value="<?= $claim_id ?>">
            <input type="hidden" name="inspection_type" value="<?= $type ?>">

            <!-- Dynamic Item List -->
            <div id="items-list">
            <?php if ($type === 'initial'): ?>
                <div class="row g-2 mb-2 item-row">
                    <div class="col-5">
                        <input type="text" name="items[0][name]" class="form-control" placeholder="Item/Room (e.g. Kitchen)" required>
                    </div>
                    <div class="col-6">
                        <input type="text" name="items[0][comment]" class="form-control" placeholder="Condition/comments" required>
                    </div>
                    <div class="col-1 text-end">
                        <span class="remove-item-btn" style="display:none;">🗑️</span>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($items as $k => $item): ?>
                <div class="row g-2 mb-2 item-row">
                    <div class="col-4">
                        <input type="text" class="form-control" value="<?= htmlspecialchars($item['item_name']) ?>" disabled>
                        <input type="hidden" name="items[<?= $k ?>][name]" value="<?= htmlspecialchars($item['item_name']) ?>">
                    </div>
                    <div class="col-4">
                        <input type="text" class="form-control" value="<?= htmlspecialchars($item['initial_comment']) ?>" disabled>
                        <input type="hidden" name="items[<?= $k ?>][initial_comment]" value="<?= htmlspecialchars($item['initial_comment']) ?>">
                    </div>
                    <div class="col-4">
                        <input type="text" name="items[<?= $k ?>][final_comment]" class="form-control" placeholder="State after client" required>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
            <?php if ($type === 'initial'): ?>
                <button type="button" id="add-item-btn" class="btn btn-outline-primary btn-sm my-2">+ Add Item</button>
            <?php endif; ?>

            <hr>
            <h5>Digital Signatures</h5>
            <p class="text-muted small">
                Please ask the client and the owner to sign below after reviewing the report.<br>
                (Both signatures required to finalize submission.)
            </p>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label>Client Signature:</label>
                    <canvas id="clientSigPad" width="300" height="100" class="sig-pad mb-2 border"></canvas>
                    <input type="hidden" name="client_signature" id="client_signature" required>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearPad('clientSigPad')">Clear</button>
                </div>
                <div class="col-md-6">
                    <label>Owner Signature:</label>
                    <canvas id="ownerSigPad" width="300" height="100" class="sig-pad mb-2 border"></canvas>
                    <input type="hidden" name="owner_signature" id="owner_signature" required>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearPad('ownerSigPad')">Clear</button>
                </div>
            </div>
            <button type="submit" class="btn custom-btn">Submit Report</button>
        </form>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- Dynamic item add/remove (initial only) ---
let itemIdx = 1;
document.getElementById('add-item-btn')?.addEventListener('click', function() {
    let itemsList = document.getElementById('items-list');
    let row = document.createElement('div');
    row.className = 'row g-2 mb-2 item-row';
    row.innerHTML = `
        <div class="col-5">
            <input type="text" name="items[${itemIdx}][name]" class="form-control" placeholder="Item/Room" required>
        </div>
        <div class="col-6">
            <input type="text" name="items[${itemIdx}][comment]" class="form-control" placeholder="Condition/comments" required>
        </div>
        <div class="col-1 text-end">
            <span class="remove-item-btn">🗑️</span>
        </div>
    `;
    itemsList.appendChild(row);
    itemIdx++;
    updateRemovers();
});
function updateRemovers() {
    document.querySelectorAll('.remove-item-btn').forEach((btn, idx) => {
        btn.style.display = idx === 0 ? 'none' : '';
        btn.onclick = function() { btn.closest('.item-row').remove(); };
    });
}
updateRemovers();

// --- Signature pad logic (plain canvas) ---
function getPadImageData(canvasId) {
    let canvas = document.getElementById(canvasId);
    return canvas.toDataURL();
}
function clearPad(canvasId) {
    let ctx = document.getElementById(canvasId).getContext('2d');
    ctx.clearRect(0, 0, 300, 100);
}
// On submit: Both signatures required!
document.getElementById('etatDesLieuxForm').addEventListener('submit', function(e){
    let cimg = getPadImageData('clientSigPad');
    let oimg = getPadImageData('ownerSigPad');
    if (cimg.length < 200 || oimg.length < 200) {
        alert('Both signatures are required.');
        e.preventDefault(); return false;
    }
    document.getElementById('client_signature').value = cimg;
    document.getElementById('owner_signature').value = oimg;
});
</script>
<script src="navbar-close.js?v=1"></script>
</body>
</html>
