# Jowhara Hotel Luggage Storage Management System
## Staff Operating Manual & Standard Operating Procedures (SOP)

---

### 1. System Overview & Access
The Jowhara Hotel Luggage Storage System is a secure, multi-branch web application designed for managing guest luggage, thermal label printing, shift handovers, and Lost & Found property.

- **Access URL**: `http://localhost/luggage_storage` (or hotel internal IP)
- **Role Hierarchy**:
  - `Admin`: Full system configuration, user management, and security settings.
  - `Manager`: Financial reports, shift Z-Reports, disposal approvals, and settings.
  - `Staff / Receptionist`: Check-in, checkout, thermal printing, and lost property logging.

---

### 2. Luggage Check-In & Thermal Tagging
1. Click **"Add Luggage"** on the top navigation bar.
2. Select or register the guest name, room number, and phone number.
3. Specify item details (Luggage Type, Quantity, Storage Zone, Shelf Location).
4. If item is High-Value ($\ge \$100$), toggle the **High-Value / Secured** checkbox.
5. Click **"Save"**.
6. On the luggage table, click **"Tag"** to open the **Thermal 58mm/80mm Label Print** layout. Attach the printed label directly to the bag.
7. Click **"Receipt"** to print the **80mm Guest Claim Receipt** for the guest.

---

### 3. Guest Digital Pass & Pre-Checkout
- Guests can scan the QR code on their printed receipt to open their **Digital Luggage Pass** on their mobile phone (`guest_pass.php`).
- **Express Pre-Checkout Alert**: 10–15 minutes before coming to the desk, guests can tap **"I'm Coming to Pick Up"** on their mobile pass.
- Front desk staff will receive a pop-up alert notification so the luggage can be retrieved from storage in advance.

---

### 4. Luggage Checkout & Fee Collection
1. Scan the guest's Tag Code or QR pass using the Barcode Scanner (`scanner.php`) or search in `luggage.php`.
2. Click **"Checkout"**.
3. Verify fee amount and select Payment Method (`Cash`, `Card`, `Waived`).
4. Have the guest sign on the touch-signature pad.
5. Click **"Confirm Checkout"**.

---

### 5. Shift Handover & Cash Balancing (Shift Z-Report)
1. At the end of every shift, go to **"Shift Handover"** (`shift_handover.php`).
2. Select the **Incoming Staff Member**.
3. Count the physical cash in the drawer and enter it into **"Counted Cash ($)"**.
4. Enter the **Opening Float ($)**.
5. The system automatically computes:
   - Expected System Cash Revenue
   - Card / Electronic Revenue
   - Cash Variance (Shortage or Surplus)
6. Click **"Submit & Print Z-Report"** to print the 80mm Shift Z-Report. Both outgoing and incoming staff must sign the printed report.

---

### 6. Multi-Branch Luggage Transfers
1. To transfer luggage to another branch or airport drop-off point, open **"Branch Transfers"** (`branch_transfers.php`).
2. Select the luggage tag, destination branch, driver/courier name, and phone.
3. Click **"Dispatch Transfer"**.
4. When the driver arrives at the receiving branch, staff click **"Receive"** to update branch ownership.

---

### 7. Lost & Found Management
- **Register Item**: Go to `add_lost_found.php`. Enter item name, category, location found, and secure storage location.
- **Claiming**: Go to `claim_lost_found.php`. For high-value items ($\ge \$100$ or Electronics/Jewelry), **ID / Passport Number is mandatory**.
- **Disposal Approval**: Only Managers and Admins can approve item disposals (`lost_found.php`).
- **Chain of Custody**: All movements (`Found → Secured → Claimed → Disposed`) are permanently recorded in custody logs.

---

### 8. System Monitoring & Backups
- **Health Check**: Access `health.php` to verify DB, disk space, and error log status.
- **Automated Backups**: Backups are automatically generated daily at 11:59 PM with MD5 checksum verification and retain for 30 days (`cron.php`).
