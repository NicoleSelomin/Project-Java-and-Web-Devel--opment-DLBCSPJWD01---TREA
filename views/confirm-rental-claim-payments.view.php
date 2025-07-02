<?php if (isset($_SESSION['message'])): ?>
  <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?>">
    <?= $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
  </div>
<?php endif; ?>

<?php foreach ($claims as $claim_id => $data): 
    $info = $data['info'];
    $payments = $data['payments'];
?>
<div class="card mb-4 shadow-sm">
    <div class="card-header bg-light d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <?php if ($info['image']): ?>
                <img src="<?= $info['image'] ?>" class="img-thumbnail me-3" alt="Property Image" style="width: 80px; height: auto;">
            <?php endif; ?>
            <div>
                <h5 class="mb-0"><?= htmlspecialchars($info['property_name']) ?></h5>
                <small class="text-muted"><?= htmlspecialchars($info['location']) ?></small><br>
                <a href="view-property.php?property_id=<?= $info['property_id'] ?>" class="btn btn-outline-primary btn-sm mt-1">View Property</a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <p><strong>Claimed on:</strong> <?= date('Y-m-d', strtotime($info['claimed_at'])) ?></p>

        <h6>Claim & Deposit Payments</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <th>Agency Invoice</th>
                        <th>Client Proof</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $pay): ?>
    <tr>
        <td><?= ucfirst($pay['payment_type']) ?></td>
        <!-- INVOICE COLUMN -->
        <td>
<?php if ($pay['payment_type'] === 'deposit'): ?>
    <?php $canEditDepositInvoice = ($data['initial_report_status'] === 'fully_signed'); ?>
    <?php if (!$canEditDepositInvoice): ?>
        <button class="btn btn-sm btn-outline-danger" disabled>Initial Report Not Yet Fully Signed</button>
    <?php elseif (!empty($pay['invoice_path']) && file_exists($pay['invoice_path']) && filesize($pay['invoice_path']) > 0): ?>
        <a href="<?= htmlspecialchars($pay['invoice_path']) ?>" target="_blank">
            View Deposit Invoice
        </a>
    <?php else: ?>
        <a href="edit-invoice.php?request_id=<?= $info['claim_id'] ?>&type=deposit" class="btn btn-sm custom-btn">
            Edit/Upload Deposit Invoice
        </a>
    <?php endif; ?>
<?php else: ?>

                <!-- for 'claim' or other payment types, your usual invoice display logic -->
                <?php if ($pay['invoice_path']): ?>
                    <a href="<?= htmlspecialchars($pay['invoice_path']) ?>" target="_blank">View Invoice</a>
                <?php else: ?>
                    <span class="text-muted">No Invoice</span>
                <?php endif; ?>
            <?php endif; ?>
        </td>


                        <!-- PROOF -->
                        <td>
                            <?= $pay['payment_proof'] ? '<a href="'.htmlspecialchars($pay['payment_proof']).'" target="_blank">View</a>' : '<span class="text-muted">Awaiting</span>' ?>
                        </td>

                        <!-- STATUS -->
                        <td>
                            <?= $pay['payment_status'] === 'confirmed' 
                                ? '<span class="badge bg-success">Confirmed</span>' 
                                : '<span class="badge bg-warning text-dark">Pending</span>' ?>
                        </td>

                        <!-- ACTION -->
                        <td>
                            <?php if (
                                !empty($pay['invoice_path']) &&
                                !empty($pay['payment_proof']) &&
                                $pay['payment_status'] !== 'confirmed'
                            ): ?>
                                <form method="POST" action="confirm-rental-claim-payments.php" class="d-inline">
                                    <input type="hidden" name="payment_id" value="<?= $pay['payment_id'] ?>">
                                    <button class="btn btn-sm btn-success">Confirm</button>
                                </form>
                            <?php elseif ($pay['payment_status'] === 'confirmed'): ?>
                                <i class="text-muted small">No action</i>
                            <?php else: ?>
                                <i class="text-muted small">Waiting on files</i>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>