<div style="text-align: center; padding: 3rem 1rem;">
    <div style="width: 80px; height: 80px; background: #dbeafe; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem;">PP</div>
    <h1 style="font-size: 1.75rem; color: #1e40af; margin-bottom: 0.5rem;">PayPal Payment Successful!</h1>
    <p style="color: #555; margin-bottom: 2rem;">Your PayPal order was completed successfully.</p>
    <?php if ($orderId): ?>
    <div style="background: #f8f9fa; border-radius: 8px; padding: 1rem; display: inline-block; margin-bottom: 1.5rem; text-align: left;">
        <div style="font-size: 0.8rem; color: #888; margin-bottom: 0.25rem;">Order ID</div>
        <code style="font-size: 0.875rem; color: #333;"><?php echo htmlspecialchars($orderId); ?></code>
    </div>
    <?php endif; ?>
    <?php if ($captured && isset($captured['status'])): ?>
    <div style="background: #d1fae5; border-radius: 8px; padding: 0.75rem 1rem; display: inline-block; margin-bottom: 1.5rem;">
        <span style="color: #065f46; font-weight: 600;">Status: <?php echo htmlspecialchars($captured['status']); ?></span>
    </div>
    <?php endif; ?>
    <div><a href="/admin/payments" class="btn btn-primary">Back to Payments</a></div>
</div>
