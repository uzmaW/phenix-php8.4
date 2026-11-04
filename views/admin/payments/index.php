<div class="page-header" style="margin-bottom: 1.5rem;">
    <h1 style="font-size: 1.5rem; color: #1a1a2e; margin: 0;">Payment Integrations</h1>
    <p style="color: #888; margin-top: 0.25rem;">Configure and test Stripe and PayPal payment processing</p>
</div>

<?php if (isset($_SESSION['payment_error'])): ?>
<div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['payment_error']); unset($_SESSION['payment_error']); ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    <!-- Stripe Card -->
    <div class="card" style="border-top: 4px solid #635bff;">
        <div class="card-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="background: #635bff; color: white; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem;">S</div>
                <div>
                    <h2 style="margin: 0; font-size: 1.1rem;">Stripe</h2>
                    <p style="margin: 0; font-size: 0.8rem; color: #888;">Hosted Checkout</p>
                </div>
            </div>
            <span class="badge <?php echo $stripeConfigured ? 'badge-success' : 'badge-info'; ?>">
                <?php echo $stripeConfigured ? 'Configured' : 'Demo Mode'; ?>
            </span>
        </div>
        <div style="padding: 0; margin-bottom: 1rem;">
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach (['Hosted payment page (no PCI burden)', 'Automatic fraud detection', 'Webhook verification', '135+ currencies supported'] as $feature): ?>
                <li style="padding: 0.4rem 0; border-bottom: 1px solid #f5f5f5; font-size: 0.875rem; color: #555;">
                    <span style="color: #10b981; margin-right: 0.5rem;">✓</span> <?php echo $feature; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if (!$stripeConfigured): ?>
        <div class="alert alert-error" style="margin-bottom: 1rem; font-size: 0.8rem; padding: 0.5rem 0.75rem;">
            Set <code>STRIPE_SECRET_KEY</code> and <code>STRIPE_PUBLISHABLE_KEY</code> in your <code>.env</code> file to enable live checkout.
        </div>
        <?php endif; ?>
        <a href="/admin/payments/stripe/checkout" class="btn btn-primary" style="background: #635bff; width: 100%; justify-content: center;">
            <?php echo $stripeConfigured ? 'Start Stripe Checkout ($99)' : 'Test Stripe (demo)'; ?>
        </a>
    </div>

    <!-- PayPal Card -->
    <div class="card" style="border-top: 4px solid #003087;">
        <div class="card-header">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="background: #003087; color: white; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem;">P</div>
                <div>
                    <h2 style="margin: 0; font-size: 1.1rem;">PayPal</h2>
                    <p style="margin: 0; font-size: 0.8rem; color: #888;">REST API v2 Orders</p>
                </div>
            </div>
            <span class="badge <?php echo $paypalConfigured ? 'badge-success' : 'badge-info'; ?>">
                <?php echo $paypalConfigured ? 'Configured' : 'Sandbox'; ?>
            </span>
        </div>
        <div style="padding: 0; margin-bottom: 1rem;">
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach (['Redirect to PayPal hosted flow', 'Capture after approval', 'Buyer protection included', 'Direct PayPal balance support'] as $feature): ?>
                <li style="padding: 0.4rem 0; border-bottom: 1px solid #f5f5f5; font-size: 0.875rem; color: #555;">
                    <span style="color: #10b981; margin-right: 0.5rem;">✓</span> <?php echo $feature; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if (!$paypalConfigured): ?>
        <div class="alert alert-error" style="margin-bottom: 1rem; font-size: 0.8rem; padding: 0.5rem 0.75rem;">
            Set <code>PAYPAL_CLIENT_ID</code> and <code>PAYPAL_CLIENT_SECRET</code> in your <code>.env</code> file to enable live payments.
        </div>
        <?php endif; ?>
        <a href="/admin/payments/paypal/checkout" class="btn btn-primary" style="background: #003087; width: 100%; justify-content: center;">
            <?php echo $paypalConfigured ? 'Pay with PayPal ($99)' : 'Test PayPal (sandbox)'; ?>
        </a>
    </div>
</div>

<!-- Integration Guide -->
<div class="card">
    <div class="card-header"><h2>Setup Guide</h2></div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <div>
            <h3 style="font-size: 0.95rem; margin-bottom: 0.75rem; color: #635bff;">Stripe Setup</h3>
            <ol style="padding-left: 1.2rem; font-size: 0.875rem; color: #555; line-height: 2;">
                <li>Create account at <strong>dashboard.stripe.com</strong></li>
                <li>Get API keys from <em>Developers → API Keys</em></li>
                <li>Add to <code>.env</code>:<br><code>STRIPE_SECRET_KEY=sk_test_...</code><br><code>STRIPE_PUBLISHABLE_KEY=pk_test_...</code><br><code>STRIPE_WEBHOOK_SECRET=whsec_...</code></li>
                <li>Set webhook URL: <code>/admin/payments/stripe/webhook</code></li>
            </ol>
        </div>
        <div>
            <h3 style="font-size: 0.95rem; margin-bottom: 0.75rem; color: #003087;">PayPal Setup</h3>
            <ol style="padding-left: 1.2rem; font-size: 0.875rem; color: #555; line-height: 2;">
                <li>Create app at <strong>developer.paypal.com</strong></li>
                <li>Get credentials from <em>My Apps &amp; Credentials</em></li>
                <li>Add to <code>.env</code>:<br><code>PAYPAL_CLIENT_ID=AXxx...</code><br><code>PAYPAL_CLIENT_SECRET=EPxx...</code></li>
                <li>Set <code>PAYPAL_SANDBOX=true</code> for testing</li>
            </ol>
        </div>
    </div>
</div>
