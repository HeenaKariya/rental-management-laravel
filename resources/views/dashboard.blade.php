@extends('layouts.app')

@section('content')
    <div class="ui-shell">
        <div class="ui-wrap">
            <header class="identity-card">
                <div class="identity-brand">
                    <div class="logo-mark">P</div>
                    <div>
                        <p class="logo-text">PropMgr</p>
                        <p class="eyebrow-text">Rental operations control center</p>
                    </div>
                </div>

                <div class="identity-actions">
                    <span class="badge badge-ink">2FA verified</span>
                    <div class="id-avatar">SA</div>
                </div>
            </header>

            <section class="hero-grid">
                <div class="hero-copy card-soft">
                    <p class="row-label">Design Baseline</p>
                    <h1 class="hero-title">UI kit installed as the default product language.</h1>
                    <p class="hero-text">
                        This dashboard now anchors future screens with the same typography, spacing,
                        badges, cards, forms, and navigation patterns from your PropMgr kit.
                    </p>
                    <div class="btn-strip">
                        <button class="btn btn-solid" type="button">Open dashboard</button>
                        <button class="btn btn-lime" type="button">Create tenant</button>
                        <button class="btn btn-ghost" type="button">Export report</button>
                    </div>
                </div>

                <div class="hero-swatches card-soft">
                    <p class="row-label">Color system</p>
                    <div class="color-grid">
                        <div class="color-swatch is-ink"><span>ink</span></div>
                        <div class="color-swatch is-base"><span>base</span></div>
                        <div class="color-swatch is-lime"><span>lime</span></div>
                        <div class="color-swatch is-violet"><span>violet</span></div>
                        <div class="color-swatch is-coral"><span>coral</span></div>
                        <div class="color-swatch is-sky"><span>sky</span></div>
                        <div class="color-swatch is-gold"><span>gold</span></div>
                        <div class="color-swatch is-green"><span>green</span></div>
                    </div>
                </div>
            </section>

            <section>
                <p class="row-label">Status badges</p>
                <div class="badge-strip">
                    <span class="badge badge-lime">Fully paid</span>
                    <span class="badge badge-gold">Partial</span>
                    <span class="badge badge-coral">Overdue</span>
                    <span class="badge badge-sky">Return pending</span>
                    <span class="badge badge-violet">v1.5</span>
                    <span class="badge badge-green">Verified</span>
                    <span class="badge badge-ink">Super admin</span>
                    <span class="badge badge-outline">Draft</span>
                </div>
            </section>

            <section>
                <p class="row-label">Stat cards</p>
                <div class="stat-grid">
                    <article class="stat-card">
                        <p class="stat-label">Properties</p>
                        <h2 class="stat-value">7</h2>
                        <p class="stat-meta"><span class="stat-pill positive">5 rented</span><span>2 vacant</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Rent due</p>
                        <h2 class="stat-value">₹4.2L</h2>
                        <p class="stat-meta"><span class="stat-pill negative">₹68K due</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Active leases</p>
                        <h2 class="stat-value">18</h2>
                        <p class="stat-meta"><span class="stat-pill warning">3 expiring</span></p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-label">Maintenance</p>
                        <h2 class="stat-value">6</h2>
                        <p class="stat-meta"><span class="stat-pill negative">2 urgent</span></p>
                    </article>
                </div>
            </section>

            <section>
                <p class="row-label">Tabs</p>
                <div class="tabs" role="tablist" aria-label="Dashboard sections">
                    <button class="tab is-active" type="button" data-ui-tab>Overview</button>
                    <button class="tab" type="button" data-ui-tab>Rent ledger</button>
                    <button class="tab" type="button" data-ui-tab>Deposits</button>
                    <button class="tab" type="button" data-ui-tab>Reports</button>
                </div>
            </section>

            <section>
                <p class="row-label">Rent activity table</p>
                <div class="table-card">
                    <div class="table-head">
                        <span>Tenant</span>
                        <span>Amount</span>
                        <span>Date</span>
                        <span>Status</span>
                    </div>

                    <div class="table-row">
                        <div class="tenant-cell">
                            <div class="avatar avatar-green">RM</div>
                            <div>
                                <div class="tenant-name">Ravi Mehta</div>
                                <div class="tenant-unit">Flat 101</div>
                            </div>
                        </div>
                        <div class="mono-text">₹12,500</div>
                        <div class="muted-text">Apr 2026</div>
                        <span class="badge badge-lime compact-badge">Fully paid</span>
                    </div>

                    <div class="table-row">
                        <div class="tenant-cell">
                            <div class="avatar avatar-gold">NT</div>
                            <div>
                                <div class="tenant-name">Neha Traders</div>
                                <div class="tenant-unit">Shop-A</div>
                            </div>
                        </div>
                        <div class="mono-text is-gold">₹8,000 / ₹18,000</div>
                        <div class="muted-text">Apr 2026</div>
                        <span class="badge badge-gold compact-badge">Partial</span>
                    </div>

                    <div class="table-row">
                        <div class="tenant-cell">
                            <div class="avatar avatar-coral">PS</div>
                            <div>
                                <div class="tenant-name">Priya Shah</div>
                                <div class="tenant-unit">Flat 203</div>
                            </div>
                        </div>
                        <div class="mono-text is-coral">₹0 + ₹2,100</div>
                        <div class="muted-text">Apr 2026</div>
                        <span class="badge badge-coral compact-badge">Overdue</span>
                    </div>

                    <div class="table-row">
                        <div class="tenant-cell">
                            <div class="avatar avatar-sky">KC</div>
                            <div>
                                <div class="tenant-name">Kiran Corp</div>
                                <div class="tenant-unit">Office 3B</div>
                            </div>
                        </div>
                        <div class="mono-text">₹6,200</div>
                        <div class="muted-text">Apr 2026</div>
                        <span class="badge badge-sky compact-badge">Return pending</span>
                    </div>
                </div>
            </section>

            <section class="two-up-grid">
                <article>
                    <p class="row-label">Pending actions</p>
                    <div class="pending-card">
                        <div class="pending-row"><span>Deposit refunds overdue</span><span class="pending-pill is-coral">2</span></div>
                        <div class="pending-row"><span>Rent returns unsettled</span><span class="pending-pill is-gold">3</span></div>
                        <div class="pending-row"><span>Agreements unsigned</span><span class="pending-pill is-gold">4</span></div>
                        <div class="pending-row"><span>KYC docs pending</span><span class="pending-pill is-neutral">7</span></div>
                        <div class="pending-row"><span>Notarized agmts pending</span><span class="pending-pill is-neutral">2</span></div>
                    </div>
                </article>

                <article>
                    <p class="row-label">Activity feed</p>
                    <div class="feed-card">
                        <div class="feed-item">
                            <div class="feed-rail"><span class="feed-dot is-green"></span><span class="feed-line"></span></div>
                            <div><p class="feed-text">Ravi Mehta signed digital agreement — Flat 101</p><p class="feed-meta">Today, 10:14 AM</p></div>
                        </div>
                        <div class="feed-item">
                            <div class="feed-rail"><span class="feed-dot is-gold"></span><span class="feed-line"></span></div>
                            <div><p class="feed-text">Rent return initiated — Kiran Corp · Office 3B</p><p class="feed-meta">Today, 9:02 AM</p></div>
                        </div>
                        <div class="feed-item">
                            <div class="feed-rail"><span class="feed-dot is-sky"></span><span class="feed-line"></span></div>
                            <div><p class="feed-text">2FA login — Arun Patel · 103.21.x.x</p><p class="feed-meta">Yesterday, 6:33 PM</p></div>
                        </div>
                        <div class="feed-item">
                            <div class="feed-rail"><span class="feed-dot is-coral"></span></div>
                            <div><p class="feed-text">₹2,100 arrears auto-carried · Flat 203</p><p class="feed-meta">Apr 1, 12:00 AM</p></div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="content-grid">
                <aside>
                    <p class="row-label">Sidebar nav</p>
                    <nav class="nav-card" aria-label="Primary">
                        <p class="nav-section">Core</p>
                        <a class="nav-item is-active" href="#">Dashboard</a>
                        <a class="nav-item" href="#">Properties</a>
                        <a class="nav-item" href="#">Units</a>
                        <a class="nav-item" href="#">Tenants &amp; KYC</a>
                        <p class="nav-section">Financial</p>
                        <a class="nav-item" href="#">Leases</a>
                        <a class="nav-item" href="#">Rent &amp; Payments</a>
                        <a class="nav-item" href="#">Rent Returns <span class="nav-chip">v1.5</span></a>
                        <a class="nav-item" href="#">Deposits</a>
                        <p class="nav-section">Other</p>
                        <a class="nav-item" href="#">E-Agreements <span class="nav-chip">v1.3</span></a>
                        <a class="nav-item" href="#">Maintenance</a>
                    </nav>
                </aside>

                <div>
                    <p class="row-label">Form inputs</p>
                    <div class="form-card">
                        <label class="field-group">
                            <span class="field-label">Tenant name</span>
                            <input class="field-input" type="text" value="Ravi Mehta">
                        </label>
                        <label class="field-group">
                            <span class="field-label">Monthly rent (₹)</span>
                            <input class="field-input" type="text" value="12,500">
                            <span class="field-hint">Instalment 2 of 2 — collected</span>
                        </label>
                        <label class="field-group">
                            <span class="field-label">Unit</span>
                            <input class="field-input is-error" type="text" value="Flat 203">
                            <span class="field-hint is-error">₹2,100 arrears · payment overdue</span>
                        </label>
                    </div>
                </div>
            </section>

            <section>
                <p class="row-label">Buttons</p>
                <div class="btn-strip">
                    <button class="btn btn-solid" type="button">Add tenant</button>
                    <button class="btn btn-lime" type="button">Mark paid</button>
                    <button class="btn btn-violet" type="button">View ledger</button>
                    <button class="btn btn-coral" type="button">Flag overdue</button>
                    <button class="btn btn-ghost" type="button">Cancel</button>
                    <button class="btn btn-ghost btn-sm" type="button">Export</button>
                    <button class="btn btn-solid btn-circle" type="button">+</button>
                </div>
            </section>

            <section>
                <p class="row-label">Toggles</p>
                <div class="toggle-list">
                    <button class="toggle-row" type="button" data-ui-toggle aria-pressed="true">
                        <span class="toggle is-on"><span class="toggle-knob"></span></span>
                        <span class="toggle-label">Email rent reminders</span>
                    </button>
                    <button class="toggle-row" type="button" data-ui-toggle aria-pressed="false">
                        <span class="toggle"><span class="toggle-knob"></span></span>
                        <span class="toggle-label">Auto-generate receipts</span>
                    </button>
                    <button class="toggle-row" type="button" data-ui-toggle aria-pressed="true">
                        <span class="toggle is-on"><span class="toggle-knob"></span></span>
                        <span class="toggle-label">2FA enforced for all users</span>
                    </button>
                </div>
            </section>

            <section>
                <p class="row-label">Quick nav modules</p>
                <div class="quick-grid">
                    <article class="quick-card">
                        <div class="quick-icon is-green"></div>
                        <h3>Rent &amp; payments ↗</h3>
                        <p>Ledger · instalments · arrears</p>
                    </article>
                    <article class="quick-card">
                        <div class="quick-icon is-coral"></div>
                        <h3>Rent returns ↗</h3>
                        <p>Pro-rata · settlement · ledger</p>
                    </article>
                    <article class="quick-card">
                        <div class="quick-icon is-sky"></div>
                        <h3>Security deposits ↗</h3>
                        <p>Sub-ledger · deductions · refund</p>
                    </article>
                    <article class="quick-card">
                        <div class="quick-icon is-violet"></div>
                        <h3>E-agreements ↗</h3>
                        <p>Templates · sign · notarized</p>
                    </article>
                    <article class="quick-card">
                        <div class="quick-icon is-gold"></div>
                        <h3>Users &amp; 2FA ↗</h3>
                        <p>OTP · lockout · backup codes</p>
                    </article>
                    <article class="quick-card">
                        <div class="quick-icon is-lime"></div>
                        <h3>Tenant portal ↗</h3>
                        <p>Self-service · KYC · receipts</p>
                    </article>
                </div>
            </section>
        </div>
    </div>
@endsection