<div style="text-align: center; padding: 3rem 1rem;">
    <div style="width: 80px; height: 80px; background: #d1fae5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2.5rem;">✓</div>
    <h1 style="font-size: 1.75rem; color: #065f46; margin-bottom: 0.5rem;">Payment Successful!</h1>
    <p style="color: #555; margin-bottom: 2rem;">Your Stripe checkout was completed successfully.</p>
    <?php if ($sessionId): ?>
    <div style="background: #f8f9fa; border-radius: 8px; padding: 1rem; display: inline-block; margin-bottom: 2rem; text-align: left;">
        <div style="font-size: 0.8rem; color: #888; margin-bottom: 0.25rem;">Session ID</div>
        <code style="font-size: 0.875rem; color: #333;"><?php echo htmlspecialchars($sessionId); ?></code>
    </div>
    <?php endif; ?>
    <div><a href="/admin/payments" class="btn btn-primary">Back to Payments</a></div>
</div>
