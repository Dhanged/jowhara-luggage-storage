<?php
require_once __DIR__ . "/layout.php";
require_login();

render_header("Help Center", "help");
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1"><?php echo __("Help & Support Desk"); ?></h1>
        <p class="text-secondary mb-0"><?php echo __("Comprehensive documentation and interactive user guide for Jowhara Hotel staff."); ?></p>
    </div>
</div>

<!-- ══════════════════ SEARCH BAR ══════════════════ -->
<div class="card mb-4">
    <div class="card-body p-4 text-center" style="background: linear-gradient(135deg, rgba(29, 111, 111, 0.05) 0%, rgba(13, 148, 136, 0.05) 100%);">
        <h4 class="fw-bold mb-2 text-dark"><?php echo __("How can we help you today?"); ?></h4>
        <p class="text-secondary small mb-3"><?php echo __("Search through our knowledge base by keywords, features, or roles."); ?></p>
        <div class="mx-auto" style="max-width: 600px;">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-secondary"></i></span>
                <input type="text" id="faqSearchInput" class="form-control border-start-0 py-2" placeholder="<?php echo __("Type keywords (e.g. 2FA, checkout, barcode, lost)..."); ?>">
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Category sidebar links -->
    <div class="col-lg-3">
        <div class="card sticky-lg-top" style="top: 80px; z-index: 10;">
            <div class="list-group list-group-flush" id="faqCategoryList">
                <button type="button" class="list-group-item list-group-item-action active d-flex align-items-center gap-2" data-target="all">
                    <i class="bi bi-grid-fill"></i> <?php echo __("All Topics"); ?>
                </button>
                <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" data-target="getting-started">
                    <i class="bi bi-rocket-takeoff"></i> <?php echo __("Getting Started"); ?>
                </button>
                <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" data-target="luggage">
                    <i class="bi bi-briefcase"></i> <?php echo __("Luggage Operations"); ?>
                </button>
                <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" data-target="lost-found">
                    <i class="bi bi-search"></i> <?php echo __("Lost & Found Desk"); ?>
                </button>
                <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" data-target="security">
                    <i class="bi bi-shield-lock"></i> <?php echo __("Security & 2FA"); ?>
                </button>
                <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" data-target="admin-mgr">
                    <i class="bi bi-person-workspace"></i> <?php echo __("Management & Reports"); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- FAQ Accordion pane -->
    <div class="col-lg-9">
        <div id="faqContainer">
            
            <!-- Category: Getting Started -->
            <div class="faq-group mb-4" id="group-getting-started">
                <h4 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2 border-bottom pb-2">
                    <i class="bi bi-rocket-takeoff-fill text-primary"></i>
                    <?php echo __("Getting Started"); ?>
                </h4>
                <div class="accordion" id="accordionGettingStarted">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#get-1">
                                <?php echo __("What is the core workflow of Jowhara Hotel Luggage Storage?"); ?>
                            </button>
                        </h2>
                        <div id="get-1" class="accordion-collapse collapse show" data-bs-parent="#accordionGettingStarted">
                            <div class="accordion-body">
                                <p><?php echo __("The system is designed for receptionists and storage porters to manage guest luggage during arrival/departure delays. The core workflow is:"); ?></p>
                                <ol class="mb-0">
                                    <li><strong><?php echo __("Register Guest"); ?>:</strong> <?php echo __("Add the guest's name, room number, contact information, and optionally scan their ID."); ?></li>
                                    <li><strong><?php echo __("Check-in Luggage"); ?>:</strong> <?php echo __("Enter bag type, quantity, specify location, take photos, and generate unique barcode tags."); ?></li>
                                    <li><strong><?php echo __("Print Label & Receipt"); ?>:</strong> <?php echo __("Attach the barcode/QR label to the bag and hand the guest receipt."); ?></li>
                                    <li><strong><?php echo __("Checkout/Release"); ?>:</strong> <?php echo __("When the guest returns, scan the QR code/barcode, confirm payment, obtain a signature, and mark as Collected."); ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#get-2">
                                <?php echo __("How do I install the Jowhara Storage App (PWA) on my mobile or desktop?"); ?>
                            </button>
                        </h2>
                        <div id="get-2" class="accordion-collapse collapse" data-bs-parent="#accordionGettingStarted">
                            <div class="accordion-body">
                                <p><?php echo __("The application supports Progressive Web App (PWA) installation for offline fallback and fast loading:"); ?></p>
                                <ul>
                                    <li><strong><?php echo __("Desktop"); ?>:</strong> <?php echo __("Click the 'Install App' button on the top right navigation bar or click the install icon inside your browser URL bar."); ?></li>
                                    <li><strong><?php echo __("Mobile (Chrome/Safari)"); ?>:</strong> <?php echo __("A prompt will suggest installing. If not, tap browser settings (three dots) and select 'Add to Home Screen'. Safari users can tap Share → 'Add to Home Screen'."); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category: Luggage Operations -->
            <div class="faq-group mb-4" id="group-luggage">
                <h4 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2 border-bottom pb-2">
                    <i class="bi bi-briefcase-fill text-success"></i>
                    <?php echo __("Luggage Operations"); ?>
                </h4>
                <div class="accordion" id="accordionLuggage">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#lug-1">
                                <?php echo __("How does the real-time capacity and zone suggestion work?"); ?>
                            </button>
                        </h2>
                        <div id="lug-1" class="accordion-collapse collapse" data-bs-parent="#accordionLuggage">
                            <div class="accordion-body">
                                <p><?php echo __("Each storage shelf has a configured capacity (e.g. Zone A Shelves A-1 to A-4). When checking in bags:"); ?></p>
                                <ul>
                                    <li><?php echo __("The system displays a heatmap indicating shelf occupancy. Avoid assigning luggage to zones near 100% capacity."); ?></li>
                                    <li><strong><?php echo __("Heavy Bags"); ?>:</strong> <?php echo __("If the weight class is set to 'Heavy', a smart recommendation appears reminding porters to store the bag on bottom shelves (A-1, B-1, etc.) for safety."); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#lug-2">
                                <?php echo __("What is the protocol for declaring High-Value items?"); ?>
                            </button>
                        </h2>
                        <div id="lug-2" class="accordion-collapse collapse" data-bs-parent="#accordionLuggage">
                            <div class="accordion-body">
                                <p><?php echo __("If a guest reports checking in expensive items (laptops, jewelry, electronics):"); ?></p>
                                <ol class="mb-0">
                                    <li><?php echo __("Toggle the 'High-Value Item Declaration' switch in the luggage form."); ?></li>
                                    <li><?php echo __("Enter the estimated declared value and description of the contents."); ?></li>
                                    <li><?php echo __("Review terms and check the 'Guest Liability Waiver Accepted' checkbox. High-value items require extra attention and special verification upon checkout."); ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#lug-3">
                                <?php echo __("How do I record a luggage transfer between zones?"); ?>
                            </button>
                        </h2>
                        <div id="lug-3" class="accordion-collapse collapse" data-bs-parent="#accordionLuggage">
                            <div class="accordion-body">
                                <p><?php echo __("To transfer luggage to another storage location:"); ?></p>
                                <p><?php echo __("Go to Luggage → Edit. Modify the Storage Zone or Shelf field and save the form. The system will automatically log the move in the luggage transfers table, capturing who moved it, when, and the previous location. You can view this history on the Chain of Custody page."); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category: Lost & Found Desk -->
            <div class="faq-group mb-4" id="group-lost-found">
                <h4 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2 border-bottom pb-2">
                    <i class="bi bi-search text-warning"></i>
                    <?php echo __("Lost & Found Desk"); ?>
                </h4>
                <div class="accordion" id="accordionLostFound">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#lf-1">
                                <?php echo __("How do I register a found item in the hotel?"); ?>
                            </button>
                        </h2>
                        <div id="lf-1" class="accordion-collapse collapse" data-bs-parent="#accordionLostFound">
                            <div class="accordion-body">
                                <p><?php echo __("When an item is found left behind in the hotel rooms or common areas:"); ?></p>
                                <ol class="mb-0">
                                    <li><?php echo __("Click 'Report Found Item' on the Lost & Found dashboard."); ?></li>
                                    <li><?php echo __("Fill in the Item Name, Category, description of where it was found, and the Finder details."); ?></li>
                                    <li><?php echo __("Specify the 'Secured Location' (e.g. Safe Box 2, Admin Locker B) to ensure proper custody."); ?></li>
                                    <li><?php echo __("Upload a photo of the item for quick visual confirmation."); ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#lf-2">
                                <?php echo __("How do I verify and process a claim request?"); ?>
                            </button>
                        </h2>
                        <div id="lf-2" class="accordion-collapse collapse" data-bs-parent="#accordionLostFound">
                            <div class="accordion-body">
                                <p><?php echo __("When a guest comes to claim a lost item:"); ?></p>
                                <ol class="mb-0">
                                    <li><?php echo __("Click the 'Claim' button next to the secured item."); ?></li>
                                    <li><?php echo __("Verify their identity. Record the claimant's ID number and type."); ?></li>
                                    <li><?php echo __("Take a snapshot verification photo of the claimant holding the item (or their identification receipt) using the webcam."); ?></li>
                                    <li><?php echo __("Have the claimant sign on the touchscreen digital signature pad."); ?></li>
                                    <li><?php echo __("Submit the form to change status to 'Claimed'. This locks the record from further modification."); ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#lf-3">
                                <?php echo __("When should I mark an item as Disposed?"); ?>
                            </button>
                        </h2>
                        <div id="lf-3" class="accordion-collapse collapse" data-bs-parent="#accordionLostFound">
                            <div class="accordion-body">
                                <p><?php echo __("If an item remains unclaimed past the hotel holding policy limit (usually 90 days), it must be disposed of:"); ?></p>
                                <p><?php echo __("Click the disposal icon (red octagon) next to the item, choose/enter the disposal reason (e.g., donated, discarded, returned to finder), and confirm. This logs the exact date and reason under audit history."); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category: Security & 2FA -->
            <div class="faq-group mb-4" id="group-security">
                <h4 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2 border-bottom pb-2">
                    <i class="bi bi-shield-lock-fill text-danger"></i>
                    <?php echo __("Security & 2FA"); ?>
                </h4>
                <div class="accordion" id="accordionSecurity">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sec-1">
                                <?php echo __("How do I set up Two-Factor Authentication (2FA)?"); ?>
                            </button>
                        </h2>
                        <div id="sec-1" class="accordion-collapse collapse" data-bs-parent="#accordionSecurity">
                            <div class="accordion-body">
                                <p><?php echo __("We highly recommend enabling 2FA for all administrative and manager accounts:"); ?></p>
                                <ol class="mb-0">
                                    <li><?php echo __("Go to your Profile (top right dropdown Menu → My Profile)."); ?></li>
                                    <li><?php echo __("Click 'Setup 2FA' under the Two-Factor section."); ?></li>
                                    <li><?php echo __("Open an authenticator app (Google Authenticator, Authy, or Microsoft Authenticator) on your smartphone."); ?></li>
                                    <li><?php echo __("Scan the QR code displayed on the screen or manually type the base32 secret code."); ?></li>
                                    <li><?php echo __("Type the 6-digit TOTP verification code from your authenticator app and click verify. Once enabled, you will be prompted for this code on subsequent logins."); ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sec-2">
                                <?php echo __("What is the Login Lockout policy?"); ?>
                            </button>
                        </h2>
                        <div id="sec-2" class="accordion-collapse collapse" data-bs-parent="#accordionSecurity">
                            <div class="accordion-body">
                                <p><?php echo __("To prevent brute-force attacks, the system locks login attempts after 5 consecutive incorrect passwords from the same IP address. The block lasts for 15 minutes. This action is registered in the security audit trails."); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category: Management & Reports -->
            <div class="faq-group mb-4" id="group-admin-mgr">
                <h4 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2 border-bottom pb-2">
                    <i class="bi bi-person-workspace text-info"></i>
                    <?php echo __("Management & Reports"); ?>
                </h4>
                <div class="accordion" id="accordionAdminMgr">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mgr-1">
                                <?php echo __("How do shift handovers work?"); ?>
                            </button>
                        </h2>
                        <div id="mgr-1" class="accordion-collapse collapse" data-bs-parent="#accordionAdminMgr">
                            <div class="accordion-body">
                                <p><?php echo __("At the end of a shift, porters and receptionists must pass operations cleanly:"); ?></p>
                                <ol class="mb-0">
                                    <li><?php echo __("Navigate to Shift → Handover."); ?></li>
                                    <li><?php echo __("Select the incoming staff member who is taking over the next shift."); ?></li>
                                    <li><?php echo __("Write clear notes regarding outstanding pick-ups, high-value checkins, or maintenance tasks."); ?></li>
                                    <li><?php echo __("Submit the handover. When the incoming staff logs in, they will be greeted by a modal alert requiring them to review and Acknowledge the handover note."); ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mgr-2">
                                <?php echo __("How do I export reports to PDF or Excel?"); ?>
                            </button>
                        </h2>
                        <div id="mgr-2" class="accordion-collapse collapse" data-bs-parent="#accordionAdminMgr">
                            <div class="accordion-body">
                                <p><?php echo __("Navigate to Reports. Apply any filters (dates, storage status, payment, etc.). Scroll to the bottom of the table to find the export options:"); ?></p>
                                <ul>
                                    <li><strong><?php echo __("Export Excel"); ?>:</strong> <?php echo __("Downloads a clean, structured .xlsx sheet compatible with Microsoft Excel and Google Sheets."); ?></li>
                                    <li><strong><?php echo __("Export PDF"); ?>:</strong> <?php echo __("Generates a clean PDF version optimized for printing and filing."); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#mgr-3">
                                <?php echo __("Where can I trigger a database backup?"); ?>
                            </button>
                        </h2>
                        <div id="mgr-3" class="accordion-collapse collapse" data-bs-parent="#accordionAdminMgr">
                            <div class="accordion-body">
                                <p><?php echo __("Admin users can perform server maintenance by navigating to Database Mgmt (under Administration sidebar). You can backup the database to a .sql file, restore older database backups, and clean logs."); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        
        <!-- Search Empty State -->
        <div id="faqEmptyState" class="card d-none py-5 text-center text-muted">
            <div class="card-body">
                <i class="bi bi-search fs-1 mb-3 text-secondary"></i>
                <h5><?php echo __("No FAQ articles found"); ?></h5>
                <p class="small text-secondary mb-0"><?php echo __("Try adjusting your keywords or selecting another category."); ?></p>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("faqSearchInput");
    const categoryButtons = document.querySelectorAll("#faqCategoryList button");
    const faqGroups = document.querySelectorAll(".faq-group");
    const faqItems = document.querySelectorAll(".accordion-item");
    const emptyState = document.getElementById("faqEmptyState");

    let currentCategory = "all";

    // Live search & category filtering
    function filterFaqs() {
        const query = searchInput.value.toLowerCase().trim();
        let visibleItemsCount = 0;

        faqGroups.forEach(group => {
            const groupId = group.id.replace("group-", "");
            const isCategoryMatch = currentCategory === "all" || currentCategory === groupId;
            let groupHasVisibleItems = false;

            const itemsInGroup = group.querySelectorAll(".accordion-item");
            itemsInGroup.forEach(item => {
                const headerText = item.querySelector(".accordion-button").textContent.toLowerCase();
                const bodyText = item.querySelector(".accordion-body").textContent.toLowerCase();
                
                const isQueryMatch = query === "" || headerText.includes(query) || bodyText.includes(query);

                if (isCategoryMatch && isQueryMatch) {
                    item.classList.remove("d-none");
                    groupHasVisibleItems = true;
                    visibleItemsCount++;
                    
                    // If searching, auto-expand items to show results
                    if (query !== "") {
                        const collapseEl = item.querySelector(".accordion-collapse");
                        const buttonEl = item.querySelector(".accordion-button");
                        if (collapseEl && !collapseEl.classList.contains("show")) {
                            collapseEl.classList.add("show");
                            buttonEl.classList.remove("collapsed");
                            buttonEl.setAttribute("aria-expanded", "true");
                        }
                    }
                } else {
                    item.classList.add("d-none");
                }
            });

            if (groupHasVisibleItems) {
                group.classList.remove("d-none");
            } else {
                group.classList.add("d-none");
            }
        });

        // Toggle empty state
        if (visibleItemsCount === 0) {
            emptyState.classList.remove("d-none");
        } else {
            emptyState.classList.add("d-none");
        }
    }

    // Category click handler
    categoryButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            categoryButtons.forEach(b => b.classList.remove("active"));
            this.classList.add("active");
            currentCategory = this.dataset.target;
            filterFaqs();
        });
    });

    // Debounce/search input trigger
    if (searchInput) {
        searchInput.addEventListener("input", filterFaqs);
    }
});
</script>
<?php
render_footer();
?>
