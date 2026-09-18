<?php
// ============================================================
// AJAX HANDLER - MUST BE AT THE VERY TOP (before any HTML)
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'next_regno') {
    require_once 'config/database.php';
    header('Content-Type: application/json');
    $batch_id = (int)($_GET['batch_id'] ?? 0);
    if (!$batch_id) {
        echo json_encode(['success' => false, 'message' => 'No batch selected']);
        exit;
    }
    try {
        $year = date('Y');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE register_no LIKE ?");
        $stmt->execute(['SC-' . $year . '-%']);
        $count = (int)$stmt->fetchColumn();
        $nextNum     = str_pad($count + 1, 5, '0', STR_PAD_LEFT);
        $register_no = 'SC-' . $year . '-' . $nextNum;
        echo json_encode([
            'success'     => true,
            'register_no' => $register_no
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    exit;
}
// ============================================================
$pageTitle  = 'Add Student';
$activePage = 'add-student';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// All active courses
$courses = $pdo->query("
    SELECT id, course_code, course_name
    FROM courses
    WHERE status = 'active'
    ORDER BY course_name
")->fetchAll();

// All active/upcoming batches
$batches = $pdo->query("
    SELECT b.id, b.batch_code, b.batch_name, b.course_fee, b.course_id, b.status,
           b.total_installments, b.installment_amount,
           c.course_code, c.course_name
    FROM batches b
    LEFT JOIN courses c ON c.id = b.course_id
    WHERE b.status IN ('active', 'upcoming')
    ORDER BY b.start_date DESC
")->fetchAll();

$success = $error = '';

/**
 * Generates a guaranteed-unique register number
 */
function generateUniqueRegNo(PDO $pdo, $year) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE register_no LIKE ? FOR UPDATE");
    $stmt->execute(['SC-' . $year . '-%']);
    $count = (int)$stmt->fetchColumn();
    $nextNum = str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    return 'SC-' . $year . '-' . $nextNum;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = date('Y');
    $maxRetries = 5;
    $attempt = 0;
    $inserted = false;

    // true only when "Pay Now" button was clicked
    $doPayNow = isset($_POST['pay_now']);

    while (!$inserted && $attempt < $maxRetries) {
        $attempt++;
        try {
            $pdo->beginTransaction();

            $register_no = generateUniqueRegNo($pdo, $year);

            // Always save the selected payment_option on the student (even if not paying now)
            $paymentOption = !empty($_POST['payment_option']) ? $_POST['payment_option'] : null;

            $stmt = $pdo->prepare("
                INSERT INTO students
                (register_no, full_name, whatsapp_no, email, address, birthday, gender, nic, batch_id, payment_option, username, password_plain, access_given, credentials_sent, status, remark, call_center_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $register_no,
                $_POST['full_name'],
                $_POST['whatsapp_no'] ?: null,
                $_POST['email'] ?: null,
                $_POST['address'] ?: null,
                $_POST['birthday'] ?: null,
                $_POST['gender'] ?: null,
                $_POST['nic'] ?: null,
                $_POST['batch_id'] ?: null,
                $paymentOption,                          // ← payment method always saved here
                $_POST['username'] ?: null,
                $_POST['password_plain'] ?: null,
                isset($_POST['access_given']) ? 1 : 0,
                isset($_POST['credentials_sent']) ? 1 : 0,
                $_POST['status'] ?? 'active',
                $_POST['remark'] ?: null,
                $_POST['call_center_agent'] ?: null,
            ]);

            $studentId = $pdo->lastInsertId();

            // Registration fee (optional)
            if (!empty($_POST['reg_fee_amount'])) {
                $stmt = $pdo->prepare("INSERT INTO registration_fees (student_id, amount, bank, ref_no, payment_date, slip_marked_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $studentId,
                    $_POST['reg_fee_amount'],
                    $_POST['reg_fee_bank'] ?: null,
                    $_POST['reg_fee_ref'] ?: null,
                    $_POST['reg_fee_date'] ?: null,
                    $_POST['call_center_agent'] ?: null,
                ]);
            }

            // ========== ACTUAL PAYMENT – only when Pay Now clicked ==========
            if ($doPayNow && !empty($_POST['inst_amount']) && !empty($paymentOption)) {
                $installmentNo = null;

                if ($paymentOption === 'one_time') {
                    $installmentNo = 1;
                } elseif ($paymentOption === 'second') {
                    $installmentNo = 2;
                } elseif ($paymentOption === 'third') {
                    $installmentNo = 3;
                } elseif ($paymentOption === 'normal') {
                    if (!empty($_POST['installment_no'])) {
                        $installmentNo = (int)$_POST['installment_no'];
                    }
                }

                if ($installmentNo !== null) {
                    $stmt = $pdo->prepare("
                        INSERT INTO payments
                        (student_id, installment_no, payment_option, amount, bank, ref_no, payment_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'paid')
                    ");
                    $stmt->execute([
                        $studentId,
                        $installmentNo,
                        $paymentOption,
                        $_POST['inst_amount'],
                        $_POST['inst_bank'] ?: null,
                        $_POST['inst_ref'] ?: null,
                        $_POST['inst_date'] ?: null,
                    ]);
                }
            }

            $pdo->commit();
            $inserted = true;

            if ($doPayNow) {
                $success = "Student + Payment saved successfully! Register No: " . htmlspecialchars($register_no);
            } else {
                $success = "Student saved successfully (Payment Option only)! Register No: " . htmlspecialchars($register_no);
            }

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000 && $attempt < $maxRetries) {
                continue;
            }
            $error = "Error: " . $e->getMessage();
            break;
        }
    }

    if (!$inserted && !$error) {
        $error = "Could not generate a unique Register No after several attempts. Please try again.";
    }
}
?>

<div class="page-header">
    <h2><i class="bi bi-person-plus"></i> Add New Student</h2>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" class="card shadow-sm" id="studentForm">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Register No (Preview)</label>
                <input type="text" id="register_no" class="form-control" readonly
                       placeholder="Select Course & Batch → Auto generated">
            </div>
            <div class="col-md-4">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">WhatsApp No</label>
                <input type="text" name="whatsapp_no" class="form-control" placeholder="07xxxxxxxx">
            </div>
            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Birthday</label>
                <input type="date" name="birthday" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select">
                    <option value="">Select</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">NIC</label>
                <input type="text" name="nic" class="form-control">
            </div>
            <div class="col-md-8">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control">
            </div>

            <!-- COURSE SELECT -->
            <div class="col-md-4">
                <label class="form-label">Course *</label>
                <select id="course_id" class="form-select" required>
                    <option value="">— Select Course —</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= $c['id'] ?>">
                            <?= htmlspecialchars($c['course_code'] . ' — ' . $c['course_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- BATCH SELECT -->
            <div class="col-md-4">
                <label class="form-label">Batch *</label>
                <select name="batch_id" id="batch_id" class="form-select" required>
                    <option value="">— First select a Course —</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Password</label>
                <input type="text" name="password_plain" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="dropped">Dropped</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Call Center Agent</label>
                <input type="text" name="call_center_agent" class="form-control">
            </div>
            <div class="col-md-4 d-flex align-items-end gap-3">
                <div class="form-check">
                    <input type="checkbox" name="access_given" class="form-check-input" id="access">
                    <label class="form-check-label" for="access">Access Given</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="credentials_sent" class="form-check-input" id="sent">
                    <label class="form-check-label" for="sent">Credentials Sent</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Remark</label>
                <textarea name="remark" class="form-control" rows="2"></textarea>
            </div>
        </div>

        <hr class="my-4">
        <h5>Registration Fee</h5>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Amount</label>
                <input type="number" name="reg_fee_amount" class="form-control" value="2000" step="0.01">
            </div>
            <div class="col-md-3">
                <label class="form-label">Bank</label>
                <input type="text" name="reg_fee_bank" class="form-control" placeholder="BOC / Sampath / HNB">
            </div>
            <div class="col-md-3">
                <label class="form-label">Ref No</label>
                <input type="text" name="reg_fee_ref" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" name="reg_fee_date" class="form-control">
            </div>
        </div>

        <hr class="my-4">
        <h5>Installment Payment (Optional)</h5>
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Payment Option</label>
                <select name="payment_option" id="payment_option" class="form-select">
                    <option value="">— Select Payment Type —</option>
                    <option value="one_time">One Time Pay</option>
                    <option value="second">2nd Pay</option>
                    <option value="third">3rd Pay</option>
                    <option value="normal">Normal Pay</option>
                </select>
            </div>

            <div class="col-md-2" id="installment_no_wrapper" style="display:none;">
                <label class="form-label">Installment No</label>
                <select name="installment_no" id="installment_no" class="form-select">
                    <option value="">— Select Batch first —</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Amount</label>
                <input type="number" name="inst_amount" id="inst_amount" class="form-control" step="0.01" readonly
                       placeholder="Pay Now click කරන්න">
            </div>
            <div class="col-md-2">
                <label class="form-label">Bank</label>
                <input type="text" name="inst_bank" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Ref No</label>
                <input type="text" name="inst_ref" class="form-control">
            </div>
            <div class="col-md-1">
                <label class="form-label">Date</label>
                <input type="date" name="inst_date" class="form-control">
            </div>
        </div>

        <div class="mt-3">
            <div class="alert alert-info mb-0 py-2">
                <i class="bi bi-info-circle"></i>
                <strong>Payment Option</strong> තෝරලා <strong>Save Student</strong> ඔබ්බොත් → option එක විතරක් save වෙනවා.<br>
                <strong>Pay Now</strong> ඔබ්බොත් → Amount auto එනවා + payment එකත් database එකට save වෙනවා.
            </div>
        </div>
    </div>

    <div class="card-footer bg-white d-flex gap-2 flex-wrap">
        <!-- Save only student + payment option (NO money payment) -->
        <button type="submit" name="save_only" value="1" class="btn btn-primary">
            <i class="bi bi-save"></i> Save Student
        </button>

        <!-- Calculate amount + save student + create paid payment -->
        <button type="submit" name="pay_now" value="1" id="payNowBtn" class="btn btn-success">
            <i class="bi bi-cash-coin"></i> Pay Now
        </button>

        <a href="students.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
const allBatches     = <?= json_encode($batches) ?>;
const courseSelect   = document.getElementById('course_id');
const batchSelect    = document.getElementById('batch_id');
const registerNo     = document.getElementById('register_no');
const installmentNo  = document.getElementById('installment_no');
const instAmount     = document.getElementById('inst_amount');
const paymentOption  = document.getElementById('payment_option');
const installmentWrapper = document.getElementById('installment_no_wrapper');
const payNowBtn      = document.getElementById('payNowBtn');

function getOrdinal(n) {
    const s = ["th", "st", "nd", "rd"];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
}

function updateInstallmentOptions(batchId) {
    installmentNo.innerHTML = '<option value="">— Select —</option>';
    if (!batchId) {
        installmentNo.innerHTML = '<option value="">— Select Batch first —</option>';
        return;
    }
    const batch = allBatches.find(b => b.id == batchId);
    if (!batch) return;

    let total = parseInt(batch.total_installments) || 12;
    if (total < 1) total = 12;
    if (total > 24) total = 24;

    for (let i = 1; i <= total; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = getOrdinal(i) + ' Installment';
        installmentNo.appendChild(opt);
    }
}

// Amount ONLY calculated when Pay Now is clicked (not on option change)
function calculateAmount() {
    const batchId = batchSelect.value;
    const option  = paymentOption.value;

    if (!batchId || !option) {
        instAmount.value = '';
        return false;
    }

    const batch = allBatches.find(b => b.id == batchId);
    if (!batch) return false;

    const courseFee = parseFloat(batch.course_fee) || 0;

    if (option === 'one_time') {
        instAmount.value = courseFee > 0 ? courseFee.toFixed(2) : '';
    }
    else if (option === 'second') {
        instAmount.value = courseFee > 0 ? (courseFee / 2).toFixed(2) : '';
    }
    else if (option === 'third') {
        instAmount.value = courseFee > 0 ? (courseFee / 3).toFixed(2) : '';
    }
    else if (option === 'normal') {
        instAmount.value = batch.installment_amount || '';
    }
    return instAmount.value !== '';
}

function toggleInstallmentFields() {
    const option = paymentOption.value;
    if (option === 'normal') {
        installmentWrapper.style.display = 'block';
    } else {
        installmentWrapper.style.display = 'none';
        installmentNo.value = '';
    }
    // IMPORTANT: do NOT auto-fill amount here
    instAmount.value = '';
    instAmount.placeholder = 'Pay Now click කරන්න';
}

paymentOption.addEventListener('change', toggleInstallmentFields);

// Pay Now button → first calculate amount, then allow form submit
payNowBtn.addEventListener('click', function (e) {
    if (!paymentOption.value) {
        e.preventDefault();
        alert('Please select a Payment Option first');
        return;
    }
    if (!batchSelect.value) {
        e.preventDefault();
        alert('Please select a Batch first');
        return;
    }
    if (paymentOption.value === 'normal' && !installmentNo.value) {
        e.preventDefault();
        alert('Please select Installment No for Normal Pay');
        return;
    }

    const ok = calculateAmount();
    if (!ok) {
        e.preventDefault();
        alert('Could not calculate amount. Check Course Fee / Installment Amount of the batch.');
        return;
    }
    // amount is filled → form will submit normally with name="pay_now"
});

courseSelect.addEventListener('change', function () {
    const courseId = this.value;
    batchSelect.innerHTML = '<option value="">— Select Batch —</option>';
    registerNo.value = '';
    updateInstallmentOptions(null);
    instAmount.value = '';

    if (!courseId) {
        batchSelect.innerHTML = '<option value="">— First select a Course —</option>';
        return;
    }

    const filtered = allBatches.filter(b => b.course_id == courseId);
    if (filtered.length === 0) {
        batchSelect.innerHTML = '<option value="">— No batches for this course —</option>';
        return;
    }

    filtered.forEach(b => {
        const opt = document.createElement('option');
        opt.value = b.id;
        opt.textContent = `${b.batch_code} — ${b.batch_name}` +
                          (b.course_fee ? ` (Fee: ${b.course_fee})` : '');
        batchSelect.appendChild(opt);
    });
});

batchSelect.addEventListener('change', function () {
    const batchId = this.value;
    registerNo.value = '';
    updateInstallmentOptions(batchId);
    instAmount.value = '';   // clear amount when batch changes

    if (!batchId) return;

    registerNo.value = 'Generating...';
    fetch(`?action=next_regno&batch_id=${batchId}`)
        .then(async res => {
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Server returned invalid response (check PHP errors)');
            }
        })
        .then(data => {
            if (data.success) {
                registerNo.value = data.register_no + '';
            } else {
                registerNo.value = '';
                alert(data.message || 'Could not generate Register No');
            }
        })
        .catch(err => {
            console.error(err);
            registerNo.value = '';
            alert('Error generating Register No\n\n' + err.message);
        });
});

toggleInstallmentFields();
</script>

<?php require_once 'includes/footer.php'; ?>