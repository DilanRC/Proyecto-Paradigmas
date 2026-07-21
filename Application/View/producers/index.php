<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TinderCows producer management">
    <title>Producers | TinderCows</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="js/producers.js" defer></script>
</head>
<body>
    <header class="top-bar"><a class="brand" href="./" aria-label="Go to the TinderCows home page"><span class="brand__icon" aria-hidden="true"><svg viewBox="0 0 48 48" role="img"><path d="M13 12 6 7c-1 7 2 10 7 11m22-6 7-5c1 7-2 10-7 11"/><path d="M11 24c0-10 5-16 13-16s13 6 13 16v7c0 7-5 11-13 11S11 38 11 31Z"/><path d="M16 29c0-4 3-6 8-6s8 2 8 6-3 7-8 7-8-3-8-7Z"/><circle cx="18" cy="20" r="2"/><circle cx="30" cy="20" r="2"/><circle cx="21" cy="29" r="1.5"/><circle cx="27" cy="29" r="1.5"/></svg></span><span>Tinder<strong>Cows</strong></span></a><span class="top-bar__module">Producer module</span></header>
    <main class="container">
        <section class="page-header" aria-labelledby="page-title"><div><span class="label">Users and security</span><h1 id="page-title">Producers</h1><p>Register and manage producer and farm information.</p></div><button class="button button--primary" id="create-button" type="button"><span aria-hidden="true">＋</span>Create producer</button></section>
        <section class="panel" aria-label="Producer list">
            <div class="tools"><label class="search"><span class="screen-reader-only">Search producer</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg><input id="producer-search" type="search" autocomplete="off" placeholder="Search by name, identification, farm, or email"></label><label class="filter"><span>Status</span><select id="status-filter"><option value="ALL">All</option><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></label></div>
            <div class="list-summary"><p id="producers-total" aria-live="polite">Loading producers…</p><button class="link-button" id="refresh-button" type="button">Refresh list</button></div>
            <div class="table-container"><table><thead><tr><th>Producer</th><th>Identification</th><th>Contact</th><th>Farm</th><th>Status</th><th><span class="screen-reader-only">Actions</span></th></tr></thead><tbody id="producers-body"></tbody></table><div class="empty-state" id="empty-state" hidden><span class="empty-state__icon" aria-hidden="true">♧</span><h2>No producers found</h2><p>Change your search or create the first producer.</p></div><div class="loading-state" id="loading-state" aria-live="polite"><span class="loader" aria-hidden="true"></span>Loading information…</div></div>
        </section>
    </main>
    <dialog class="modal" id="producer-modal" aria-labelledby="modal-title"><form id="producer-form" novalidate><div class="modal__header"><div><span class="label" id="modal-subtitle">New record</span><h2 id="modal-title">Create producer</h2></div><button class="close-button" id="close-modal" type="button" aria-label="Close form">×</button></div><div class="modal__content"><input type="hidden" id="producer-id" name="producer_id"><div class="form-grid">
        <label class="field"><span>Identification type <b aria-hidden="true">*</b></span><select id="identification-type" name="identification_type" required><option value="">Select</option><option value="NATIONAL_ID">National ID</option><option value="LEGAL_ID">Legal ID</option><option value="DIMEX">DIMEX</option><option value="NITE">NITE</option></select><small class="field__error" data-error-for="identification_type"></small></label>
        <label class="field"><span>Identification number <b aria-hidden="true">*</b></span><input id="identification-number" name="identification_number" type="text" inputmode="numeric" maxlength="20" autocomplete="off" required><small class="field__error" data-error-for="identification_number"></small></label>
        <label class="field field--full"><span>Full name or legal name <b aria-hidden="true">*</b></span><input id="name" name="name" type="text" maxlength="150" autocomplete="name" required><small class="field__error" data-error-for="name"></small></label>
        <label class="field"><span>Farm name</span><input id="farm-name" name="farm_name" type="text" maxlength="150"><small class="field__error" data-error-for="farm_name"></small></label>
        <label class="field"><span>Phone <b aria-hidden="true">*</b></span><input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" placeholder="88888888" required><small class="field__error" data-error-for="phone"></small></label>
        <label class="field field--full"><span>Email <b aria-hidden="true">*</b></span><input id="email" name="email" type="email" maxlength="150" autocomplete="email" required><small class="field__error" data-error-for="email"></small></label>
        <label class="field field--full"><span>Address <b aria-hidden="true">*</b></span><textarea id="address" name="address" maxlength="255" rows="3" required></textarea><small class="field__error" data-error-for="address"></small></label>
        <label class="field" id="status-group"><span>Status <b aria-hidden="true">*</b></span><select id="status" name="status" required><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select><small class="field__error" data-error-for="status"></small></label>
    </div><p class="form-note"><b aria-hidden="true">*</b> Required fields</p></div><div class="modal__actions"><button class="button button--secondary" id="cancel-form" type="button">Cancel</button><button class="button button--primary" id="save-producer" type="submit">Save producer</button></div></form></dialog>
    <dialog class="modal modal--confirmation" id="deactivate-modal" aria-labelledby="deactivate-title"><div class="confirmation__icon" aria-hidden="true">!</div><h2 id="deactivate-title">Deactivate producer</h2><p id="deactivate-message">The producer will no longer appear among active records.</p><div class="modal__actions"><button class="button button--secondary" id="cancel-deactivation" type="button">Cancel</button><button class="button button--danger" id="confirm-deactivation" type="button">Deactivate</button></div></dialog>
    <div class="notification" id="notification" role="status" aria-live="polite" hidden></div>
</body>
</html>
