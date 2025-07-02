<?php
session_start();
require 'db_connect.php';

// ---- AUTH ----
$staff_id = $_SESSION['staff_id'] ?? null;
$role = strtolower(trim($_SESSION['role'] ?? ''));
if (!$staff_id || !in_array($role, ['general manager', 'property manager'])) {
    http_response_code(403); exit("Access denied.");
}

// ---- GET CONTRACT ----
$request_id = $_GET['request_id'] ?? null;
if (!$request_id) {
    http_response_code(400); exit("Missing request ID.");
}

// 1. Get basic info
$stmt = $pdo->prepare("
    SELECT r.*, s.slug
    FROM owner_service_requests r
    JOIN services s ON r.service_id = s.service_id
    WHERE r.request_id = ?
");
$stmt->execute([$request_id]);
$row_basic = $stmt->fetch();
if (!$row_basic) exit("No owner service request found.");

$slug = $row_basic['slug'];
$contract_path = $row_basic['owner_contract_path'] ?? '';
$locked = $row_basic['contract_locked'] ?? 0;

// 2. Map slug to template
$template_map = [
    'brokerage'                  => 'brokerage-agreement-template-en.html',
    'rental_property_management' => 'rental-management-agreement-template-en.html'
];
$template_file = $template_map[$slug] ?? null;

// 3. Get all details for placeholders
$stmt = $pdo->prepare("
    SELECT 
        r.*, s.slug,
        ou.full_name AS owner_name, ou.email AS email, ou.phone_number AS phone_number,
        st.full_name AS manager_name,
        br.property_type AS br_property_type,
        rd.property_type AS rd_property_type
    FROM owner_service_requests r
    JOIN services s ON r.service_id = s.service_id
    JOIN owners o ON r.owner_id = o.owner_id
    JOIN users ou ON o.user_id = ou.user_id
    LEFT JOIN staff st ON st.role = 'general manager'
    LEFT JOIN brokerage_details br ON br.request_id = r.request_id
    LEFT JOIN rental_property_management_details rd ON rd.request_id = r.request_id
    WHERE r.request_id = ?
");
$stmt->execute([$request_id]);
$row = $stmt->fetch();
if (!$row) exit("No owner service request details found.");

// 4. Build placeholder replacements
$default_agency = [
    '{{AGENCY_NAME}}'          => 'TRUSTED REAL ESTATE AGENCY - TREA',
    '{{AGENCY_ADDRESS}}'       => 'Aibatin 2, Cotonou, Benin',
    '{{AGENCY_PHONE}}'         => '(+229) 0100000000',
    '{{AGENCY_EMAIL_ADDRESS}}' => 'info@trea.com',
    '{{MANAGER_NAME}}'         => $row['manager_name'] ?? 'Nicole HouinatoI',
];
$variables = $default_agency;
foreach ($row as $k => $v) {
    $variables['{{' . strtoupper($k) . '}}'] = htmlspecialchars($v ?? '');
}
$variables['{{PROPERTY_TYPE}}'] = $slug === 'brokerage' ? htmlspecialchars($row['br_property_type'] ?? '') : ($slug === 'rental_property_management' ? htmlspecialchars($row['rd_property_type'] ?? '') : '');

// 5. Load contract content
if ($contract_path && file_exists($contract_path)) {
    $content = file_get_contents($contract_path);
} elseif ($template_file && file_exists($template_file)) {
    $content = strtr(file_get_contents($template_file), $variables);
    $locked = 0;
} else {
    $content = '<div class="alert alert-danger">No contract template found for this service.</div>';
}

// 6. Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    $html = $_POST['contract_html'] ?? '';

    function slugify($string) {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower(trim($string)));
    }
    $ownerFolder   = $row['owner_id'] . '_' . slugify($row['owner_name']);
    $serviceFolder = $row['service_id'] . '_' . slugify($row['slug']);
    $requestFolder = 'request_' . $request_id;
    $base_folder = __DIR__ . "/uploads/owner/$ownerFolder/applications/$serviceFolder/$requestFolder/";
    if (!is_dir($base_folder)) mkdir($base_folder, 0777, true);

    $contract_filename = 'contract.html';
    $contract_file_path = $base_folder . $contract_filename;
    file_put_contents($contract_file_path, $html);

    $rel_path = "uploads/owner/$ownerFolder/applications/$serviceFolder/$requestFolder/$contract_filename";

    // Extract additional placeholders
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    function getPlaceholderValue($doc, $placeholder) {
        $xpath = new DOMXPath($doc);
        $elements = $xpath->query("//*[contains(text(), '$placeholder')]");
        if ($elements->length > 0) {
            preg_match('/>(.*?)</', $doc->saveHTML($elements[0]), $matches);
            return trim($matches[1] ?? '');
        }
        return null;
    }

    $start_date = getPlaceholderValue($doc, '{{CONTRACT_START_DATE}}');
    $end_date = getPlaceholderValue($doc, '{{CONTRACT_END_DATE}}');
    $revision_freq = intval(getPlaceholderValue($doc, '{{REVISION_FREQUENCY}}'));
    $payment_freq = getPlaceholderValue($doc, '{{PAYMENT_FREQUENCY}}');
    $revision_unit = 'year';
    $next_revision_date = $start_date ? date('Y-m-d', strtotime("$start_date +$revision_freq $revision_unit")) : null;

    $lock = ($role === 'general manager' && isset($_POST['lock_contract'])) ? 1 : 0;

    $stmt = $pdo->prepare("
        UPDATE owner_service_requests
        SET owner_contract_path = ?, contract_locked = ?,
            contract_start_date = ?, contract_end_date = ?,
            revision_frequency = ?, revision_unit = ?,
            next_revision_date = ?, payment_frequency = ?,
            updated_at = NOW()
        WHERE request_id = ?
    ");
    $stmt->execute([
        $rel_path, $lock, $start_date, $end_date,
        $revision_freq, $revision_unit, $next_revision_date, $payment_freq, $request_id
    ]);

    header("Location: edit-contract.php?request_id=$request_id" . ($lock ? "&locked=1" : "&saved=1"));
    exit();
}

$page_title = 'Edit ' . ucwords(str_replace('_', ' ', $slug)) . ' Contract';
$can_unlock = $locked && $row['next_revision_date'] && date('Y-m-d') >= $row['next_revision_date'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_contract']) && $role === 'general manager') {
    $stmt = $pdo->prepare("UPDATE owner_service_requests SET contract_locked = 0, next_revision_date = NULL WHERE request_id = ?");
    $stmt->execute([$request_id]);
    header("Location: edit-contract.php?request_id=$request_id&unlocked=1");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=<?= time() ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

<?php include 'header.php'; ?>

<div class="container-fluid flex-grow-1 py-4">
    <div class="row">
    <main class="col-12 col-md-11 ms-lg-5">
    <div class="container py-5">
        <h2><?= htmlspecialchars($page_title) ?></h2>
        <p class="text-muted">You can modify the contract below. Once locked, no further edits are allowed and parties can sign.</p>

        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success">Changes saved.</div>
        <?php elseif (isset($_GET['locked'])): ?>
            <div class="alert alert-success">Contract locked for signing.</div>
        <?php endif; ?>

        <form method="POST">
            <?php if (!$locked): ?>
                <div id="editor"><?= $content ?></div>
                <input type="hidden" id="contract_html" name="contract_html">
                <div class="mt-3">
                    <button type="submit" class="btn btn-success me-2" onclick="saveDraft(event)">Save Changes</button>
                    <?php if ($role === 'general manager'): ?>
                        <button type="submit" name="lock_contract" value="1" class="btn btn-danger" onclick="lockAndSave(event)">Lock for Signing</button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="border p-3 bg-white" style="min-height:600px"><?= $content ?></div>
                <div class="alert alert-info mt-3">Contract is locked. Awaiting signatures.</div>
            <?php endif; ?>
        </form>
        <a href="manage-service-requests.php" class="btn btn-secondary mt-4">Back to Service Requests</a>
    </div>
    </main>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
<?php if (!$locked): ?>
<script>
    var quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link'],
                ['clean']
            ]
        }
    });
    function saveDraft(e) {
        document.getElementById('contract_html').value = quill.root.innerHTML;
    }
    function lockAndSave(e) {
        document.getElementById('contract_html').value = quill.root.innerHTML;
    }
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script to close main navbar on small screen-->
<script src="navbar-close.js?v=1"></script>

</body>
</html>
