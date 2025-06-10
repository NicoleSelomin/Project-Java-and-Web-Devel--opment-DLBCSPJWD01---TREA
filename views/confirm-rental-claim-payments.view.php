<?php if (isset($_SESSION['message'])): ?>
  <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?>">
    <?= $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
  </div>
<?php endif; ?>

<?php foreach ($claims as $claim_id => $data): 
    $info = $data['info'];
    $payments = $data['payments'];
?>
<div class="card mb-4">
    <div class="card-header bg-light d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <?php if ($info['image']): ?>
                <img src="<?= $info['image'] ?>" class="img-thumbnail me-3" alt="Property Image" style="width: 80px; height: auto;">
            <?php endif; ?>
            <div>
                <h5 class="mb-0"><?= htmlspecialchars($info['property_name']) ?></h5>
                <small class="text-muted"><?= htmlspecialchars($info['location']) ?></small>
                <a href="view-property.php?property_id=<?= $info['property_id'] ?>" class="btn btn-outline-primary btn-sm">View Property</a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <p><strong>Claimed on:</strong> <?= date('Y-m-d', strtotime($info['claimed_at'])) ?></p>

        <h6>Claim & Deposit Payments</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Invoice</th>
                        <th>Proof</th>
                        <th>Action</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $pay): ?>
                    <tr>
                        <td><?= ucfirst($pay['payment_type']) ?></td>
                        <td>
                            <?php if ($pay['invoice_path']): ?>
                                <a href="<?= $pay['invoice_path'] ?>" target="_blank">Invoice</a>
                            <?php elseif (($pay['payment_type'] === 'claim') || ($pay['payment_type'] === 'deposit' && $info['meeting_report_path'])): ?>
                                <form method="POST" action="confirm-rental-claim-payments.php" enctype="multipart/form-data">
                                    <input type="hidden" name="payment_id" value="<?= $pay['payment_id'] ?>">
                                    <input type="file" name="invoice_file" required class="form-control form-control-sm">
                                    <button class="btn btn-sm btn-secondary mt-1">Upload</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Not Uploaded</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $pay['payment_proof'] ? '<a href="'.$pay['payment_proof'].'" target="_blank">Proof</a>' : '<span class="text-muted">None</span>' ?>
                        </td>
                        <td>
                            <?php if ($pay['payment_status'] === 'confirmed'): ?>
                                <span class="text-success">Confirmed</span>
                            <?php elseif (!empty($pay['invoice_path']) && !empty($pay['payment_proof'])): ?>
                                <form method="POST" action="confirm-rental-claim-payments.php">
                                    <input type="hidden" name="payment_id" value="<?= $pay['payment_id'] ?>">
                                    <button class="btn btn-sm btn-success">Confirm</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">Waiting for invoice & proof</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $pay['payment_status'] === 'confirmed' 
                                ? '<span class="text-success">Confirmed</span>' 
                                : '<span class="text-warning">Pending</span>' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>
