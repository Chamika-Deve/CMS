<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
enforce_page_access('purchases.php');

$user = $_SESSION['user'];
$role = $user['role'] ?? 'Cashier';
$msg = '';
$msg_type = 'success';
$currency_symbol = 'Rs.'; // Default; overwritten by settings fetch below

// AJAX Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'lookup_product' && $pdo) {
        $code = trim($_POST['code'] ?? '');
        $stmt = $pdo->prepare("SELECT id, name, product_code, cost_price, reorder_level FROM products WHERE product_code = ? OR ean = ? OR upc = ?");
        $stmt->execute([$code, $code, $code]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product) {
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found. Please verify barcode.']);
        }
        exit;
    }

    if ($action === 'add_serial' && $pdo) {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $serial = trim($_POST['serial_number'] ?? '');
        if ($product_id < 1 || $serial === '' || strlen($serial) > 100) {
            echo json_encode(['success' => false, 'message' => 'A valid product and serial number are required.']);
            exit;
        }
        try {
            $chk = $pdo->prepare("SELECT id FROM product_serials WHERE serial_number = ?");
            $chk->execute([$serial]);
            if ($chk->fetch()) {
                echo json_encode(['success' => false, 'message' => "Serial '$serial' already exists!"]);
            } else {
                $ins = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, status) VALUES (?, ?, 'in_stock')");
                $ins->execute([$product_id, $serial]);
                echo json_encode(['success' => true, 'message' => "Added '$serial'"]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => safe_error_message($e)]);
        }
        exit;
    }

    if ($action === 'get_po_details' && $pdo) {
        $po_id = (int)($_POST['po_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("
                SELECT p.*, s.name as supplier_name, s.payment_terms as supp_terms, s.phone as supp_phone, s.email as supp_email, s.address as supp_address
                FROM purchases p
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                WHERE p.id = ?
            ");
            $stmt->execute([$po_id]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);

            $items_stmt = $pdo->prepare("
                SELECT pi.*, 
                       COALESCE(pi.received_quantity, 0) as received_qty,
                       GREATEST(0, pi.quantity - COALESCE(pi.received_quantity, 0)) as remaining_qty,
                       pr.name as product_name, pr.product_code
                FROM purchase_items pi
                LEFT JOIN products pr ON pi.product_id = pr.id
                WHERE pi.purchase_id = ?
            ");
            $items_stmt->execute([$po_id]);
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'po' => $po, 'items' => $items]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => safe_error_message($e)]);
        }
        exit;
    }
}

// ── Supplier Payment AJAX Handlers ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // Get payment history for a supplier or a specific PO
    if ($action === 'get_supplier_payments' && $pdo) {
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $po_id       = (int)($_POST['po_id'] ?? 0);
        try {
            // Supplier summary
            $s = $pdo->prepare("SELECT name, balance_due FROM suppliers WHERE id = ?");
            $s->execute([$supplier_id]);
            $supplier = $s->fetch(PDO::FETCH_ASSOC);

            // PO-level summary if po_id given
            $po_info = null;
            if ($po_id > 0) {
                $ps = $pdo->prepare("SELECT invoice_no, total_amount, amount_paid, payment_status FROM purchases WHERE id = ?");
                $ps->execute([$po_id]);
                $po_info = $ps->fetch(PDO::FETCH_ASSOC);
            }

            // Payment history
            $ph = $pdo->prepare("
                SELECT sp.*, p.invoice_no as po_number
                FROM supplier_payments sp
                LEFT JOIN purchases p ON sp.purchase_id = p.id
                WHERE sp.supplier_id = ?
                " . ($po_id > 0 ? "AND sp.purchase_id = ?" : "") . "
                ORDER BY sp.payment_date DESC, sp.id DESC
                LIMIT 50
            ");
            $params = $po_id > 0 ? [$supplier_id, $po_id] : [$supplier_id];
            $ph->execute($params);
            $payments = $ph->fetchAll(PDO::FETCH_ASSOC);

            // Per-PO outstanding for this supplier
            $po_list = $pdo->prepare("
                SELECT id, invoice_no, purchase_date, total_amount, amount_paid,
                       (total_amount - amount_paid) as balance_remaining, payment_status, status
                FROM purchases
                WHERE supplier_id = ? AND payment_status != 'Paid'
                ORDER BY purchase_date DESC
                LIMIT 20
            ");
            $po_list->execute([$supplier_id]);
            $outstanding_pos = $po_list->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'         => true,
                'supplier'        => $supplier,
                'po_info'         => $po_info,
                'payments'        => $payments,
                'outstanding_pos' => $outstanding_pos,
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => safe_error_message($e)]);
        }
        exit;
    }

    exit; // fallthrough guard for payment AJAX block
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if (in_array($action, ['approve_po', 'delete_po'], true)
        && !in_array($role, ['Admin', 'Manager'], true)) {
        abort_request(403, 'Only a manager or administrator may perform this purchase action.');
    }

    // 1. Create Purchase Order
    if ($action === 'create_po' && $pdo) {
        try {
            $po_number = 'PO-' . date('ymd') . '-' . random_int(1000, 9999);
            $supplier_id = (int)($_POST['supplier_id'] ?? 0);
            $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');
            $expected_date = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : date('Y-m-d', strtotime('+7 days'));
            $shipping_cost = max(0, (float)($_POST['shipping_cost'] ?? 0));
            $tax_rate = max(0, min(100, (float)($_POST['tax_rate'] ?? 0)));
            $notes = trim($_POST['notes'] ?? '');
            $items = json_decode($_POST['items_json'] ?? '[]', true);

            if ($supplier_id < 1 || !is_array($items) || $items === []) {
                throw new InvalidArgumentException('Select a supplier and add at least one product line.');
            }
            foreach ([$purchase_date, $expected_date] as $dateValue) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
                if (!$date || $date->format('Y-m-d') !== $dateValue) {
                    throw new InvalidArgumentException('Purchase and delivery dates must be valid dates.');
                }
            }

            $supplierCheck = $pdo->prepare('SELECT id FROM suppliers WHERE id = ?');
            $supplierCheck->execute([$supplier_id]);
            if (!$supplierCheck->fetchColumn()) {
                throw new InvalidArgumentException('The selected supplier no longer exists.');
            }

            $normalizedItems = [];
            $subtotal = 0.0;
            $productCheck = $pdo->prepare('SELECT id FROM products WHERE id = ?');
            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $quantity = (int)($item['qty'] ?? 0);
                $unitCost = (float)($item['cost'] ?? -1);
                if ($productId < 1 || $quantity < 1 || $unitCost < 0) {
                    throw new InvalidArgumentException('Every PO line needs a valid product, quantity, and cost.');
                }
                $productCheck->execute([$productId]);
                if (!$productCheck->fetchColumn()) {
                    throw new InvalidArgumentException('A selected PO product no longer exists.');
                }
                $normalizedItems[] = [$productId, $quantity, $unitCost];
                $subtotal += $quantity * $unitCost;
            }

            $tax_amount = round($subtotal * ($tax_rate / 100), 2);
            $total_amount = round($subtotal + $tax_amount + $shipping_cost, 2);
            $status = ($total_amount > 1000 && !in_array($role, ['Admin', 'Manager'], true))
                ? 'Submitted'
                : 'Approved';

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO purchases (supplier_id, invoice_no, purchase_date, total_amount, status, expected_delivery_date, shipping_cost, tax_rate, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$supplier_id, $po_number, $purchase_date, $total_amount, $status, $expected_date, $shipping_cost, $tax_rate, $notes]);
            $purchase_id = (int)$pdo->lastInsertId();

            $itemStatement = $pdo->prepare('INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, received_quantity) VALUES (?, ?, ?, ?, 0)');
            foreach ($normalizedItems as [$productId, $quantity, $unitCost]) {
                $itemStatement->execute([$purchase_id, $productId, $quantity, $unitCost]);
            }

            $logStatement = $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, table_name, record_id, details) VALUES (?, 'Create PO', 'Purchasing', 'purchases', ?, ?)");
            $logStatement->execute([$user['id'] ?? null, $purchase_id, "Created {$po_number}; total {$total_amount}"]);
            $pdo->commit();

            $msg = "Purchase Order #$po_number created successfully! Status: $status";
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = 'Error creating PO: ' . safe_error_message($exception);
            $msg_type = 'error';
        }
    }

    // 2. Approve or Reject PO (Manager/Admin)
    if ($action === 'approve_po' && $pdo && in_array($role, ['Admin', 'Manager'])) {
        try {
            $po_id = (int)($_POST['po_id'] ?? 0);
            $decision = $_POST['decision'] ?? '';
            if ($po_id < 1 || !in_array($decision, ['Approved', 'Rejected'], true)) {
                throw new InvalidArgumentException('Invalid purchase approval decision.');
            }
            $stmt = $pdo->prepare("UPDATE purchases SET status = ?, updated_at = NOW() WHERE id = ? AND status IN ('Draft', 'Submitted', 'Pending')");
            $stmt->execute([$decision, $po_id]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('This purchase order is not awaiting approval.');
            }
            $msg = "Purchase Order #$po_id status updated to $decision.";
        } catch (Exception $e) {
            $msg = "Error: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    // 3. Mark PO Sent to Supplier
    if ($action === 'send_po' && $pdo) {
        try {
            $po_id = (int)($_POST['po_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE purchases SET status = 'Sent to Supplier', updated_at = NOW() WHERE id = ? AND status = 'Approved'");
            $stmt->execute([$po_id]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Only an approved purchase order can be sent.');
            }
            $msg = "Purchase Order #$po_id marked as Sent to Supplier (Locked).";
        } catch (Exception $e) {
            $msg = "Error: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    // 4. Delete / Cancel PO
    if ($action === 'delete_po' && $pdo && in_array($role, ['Admin', 'Manager'])) {
        try {
            $po_id = (int)($_POST['po_id'] ?? 0);
            $pdo->beginTransaction();
            $chk = $pdo->prepare('SELECT status FROM purchases WHERE id = ? FOR UPDATE');
            $chk->execute([$po_id]);
            $curr_st = $chk->fetchColumn();
            if (!$curr_st) {
                throw new RuntimeException('Purchase order not found.');
            }
            if (in_array($curr_st, ['Received', 'Partially Received'], true)) {
                throw new RuntimeException('Cannot delete a PO that has already received stock.');
            }
            $pdo->prepare('DELETE FROM purchase_items WHERE purchase_id = ?')->execute([$po_id]);
            $pdo->prepare('DELETE FROM purchases WHERE id = ?')->execute([$po_id]);
            $pdo->commit();
            $msg = "Purchase Order #$po_id deleted successfully.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = "Error deleting PO: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    // 5. Goods Received Note (GRN) — Strict Validation & Fulfillment Tracking
    if ($action === 'receive_grn' && $pdo) {
        try {
            $po_id = (int)$_POST['po_id'];
            $product_id = (int)$_POST['product_id'];
            $received_qty = (int)($_POST['received_qty'] ?? 0);
            $is_non_serialized = isset($_POST['is_non_serialized']) ? 1 : 0;
            $serials_raw = trim($_POST['serials_list'] ?? '');

            if ($po_id <= 0) {
                throw new Exception("Please select an active Purchase Order.");
            }
            if ($product_id <= 0) {
                throw new Exception("Please select a valid product to receive.");
            }
            if ($received_qty <= 0) {
                throw new Exception("Received quantity must be at least 1.");
            }

            $pdo->beginTransaction();

            // Lock the PO line so concurrent GRNs cannot over-receive it.
            $stmt_item_chk = $pdo->prepare("
                SELECT id, quantity, COALESCE(received_quantity, 0) as received_qty, unit_cost
                FROM purchase_items
                WHERE purchase_id = ? AND product_id = ?
                FOR UPDATE
            ");
            $stmt_item_chk->execute([$po_id, $product_id]);
            $po_item = $stmt_item_chk->fetch(PDO::FETCH_ASSOC);

            if (!$po_item) {
                throw new Exception("The selected product does not belong to this Purchase Order.");
            }

            $ordered_qty = (int)$po_item['quantity'];
            $already_received = (int)$po_item['received_qty'];
            $remaining_qty = max(0, $ordered_qty - $already_received);

            if ($received_qty > $remaining_qty) {
                throw new Exception("Cannot receive $received_qty units! Only $remaining_qty unit(s) remaining on this PO (Ordered: $ordered_qty, Already Received: $already_received).");
            }

            $serials = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $serials_raw)));

            if (!$is_non_serialized) {
                // Strict Serial / IMEI Validation
                if (empty($serials)) {
                    throw new Exception("Validation Error: Serial numbers/IMEIs are required for this device. Please enter or scan $received_qty unique serial number(s).");
                }

                if (count($serials) !== $received_qty) {
                    throw new Exception("Quantity Mismatch: You specified $received_qty unit(s) to receive, but provided " . count($serials) . " serial number(s). Please provide exactly $received_qty unique serial numbers (one per line).");
                }

                foreach ($serials as $serial) {
                    if ($serial === '' || strlen($serial) > 100) {
                        throw new InvalidArgumentException('Each serial number must contain 1–100 characters.');
                    }
                }

                // Check for duplicates inside entered list
                $unique_serials = array_unique($serials);
                if (count($unique_serials) !== count($serials)) {
                    $counts = array_count_values($serials);
                    $dups = array_keys(array_filter($counts, fn($c) => $c > 1));
                    throw new Exception("Duplicate serial number in input list: '" . implode(', ', $dups) . "'. All serials must be unique.");
                }

                // Check if any serial already exists in database
                $placeholders = implode(',', array_fill(0, count($serials), '?'));
                $stmt_db_dup = $pdo->prepare("SELECT serial_number, status FROM product_serials WHERE serial_number IN ($placeholders)");
                $stmt_db_dup->execute(array_values($serials));
                $existing = $stmt_db_dup->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($existing)) {
                    $dup_list = array_map(fn($e) => $e['serial_number'] . " (" . $e['status'] . ")", $existing);
                    throw new Exception("Serial number(s) already exist in database: " . implode(', ', $dup_list) . ". Please check and re-scan.");
                }

                // Insert verified serials
                $stmt_sn = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, purchase_id, status) VALUES (?, ?, ?, 'in_stock')");
                foreach ($serials as $sn) {
                    $stmt_sn->execute([$product_id, $sn, $po_id]);
                }
            } else {
                // Bulk non-serialized items (e.g., thermal paste, cables, screws)
                $stmt_sn = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, purchase_id, status) VALUES (?, ?, ?, 'in_stock')");
                for ($i = 0; $i < $received_qty; $i++) {
                    $auto_sn = 'BATCH-' . date('ymd') . '-' . $po_id . '-' . $i . '-' . bin2hex(random_bytes(3));
                    $stmt_sn->execute([$product_id, $auto_sn, $po_id]);
                }
            }

            // Update purchase_items received_quantity
            $stmt_up_item = $pdo->prepare("UPDATE purchase_items SET received_quantity = received_quantity + ? WHERE id = ?");
            $stmt_up_item->execute([$received_qty, $po_item['id']]);

            // Check overall PO fulfillment status
            $stmt_all_items = $pdo->prepare("SELECT SUM(quantity) as total_ord, SUM(COALESCE(received_quantity, 0)) as total_rec FROM purchase_items WHERE purchase_id = ?");
            $stmt_all_items->execute([$po_id]);
            $totals = $stmt_all_items->fetch(PDO::FETCH_ASSOC);

            $tot_ord = (int)($totals['total_ord'] ?? 0);
            $tot_rec = (int)($totals['total_rec'] ?? 0);
            $new_st = ($tot_rec >= $tot_ord && $tot_ord > 0) ? 'Received' : 'Partially Received';

            $stmt_up_po = $pdo->prepare("UPDATE purchases SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt_up_po->execute([$new_st, $po_id]);

            // Update supplier balance due for the received item cost
            $received_cost = $received_qty * (float)$po_item['unit_cost'];
            $stmt_amt = $pdo->prepare("SELECT supplier_id FROM purchases WHERE id = ?");
            $stmt_amt->execute([$po_id]);
            $supp_id = $stmt_amt->fetchColumn();
            if ($supp_id) {
                $stmt_supp = $pdo->prepare("UPDATE suppliers SET balance_due = balance_due + ? WHERE id = ?");
                $stmt_supp->execute([$received_cost, $supp_id]);
            }

            $pdo->commit();
            $msg = "GRN Confirmed: $received_qty unit(s) added to active stock! Total PO Fulfillment: $tot_rec / $tot_ord units ($new_st).";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = "Error receiving goods: " . safe_error_message($e);
            $msg_type = 'error';
        }
    }

    // 6. Record Purchase Return
    if ($action === 'create_return' && $pdo) {
        try {
            $return_no = 'PR-' . date('ymd') . '-' . random_int(1000, 9999);
            $supplier_id = (int)($_POST['supplier_id'] ?? 0);
            $product_id = (int)($_POST['product_id'] ?? 0);
            $serial_no = trim($_POST['serial_number'] ?? '');
            $qty = (int)($_POST['quantity'] ?? 1);
            $refund_amount = max(0, (float)($_POST['refund_amount'] ?? 0));
            $reason = trim($_POST['reason'] ?? 'Defective on arrival');
            $refund_type = $_POST['refund_type'] ?? 'Credit Note';
            $allowed_refund_types = ['Credit Note', 'Cash Refund', 'Replacement'];

            if ($supplier_id < 1 || $product_id < 1 || $qty < 1 || $reason === '') {
                throw new InvalidArgumentException('Supplier, product, quantity, and return reason are required.');
            }
            if (!in_array($refund_type, $allowed_refund_types, true)) {
                throw new InvalidArgumentException('Invalid purchase return type.');
            }
            if ($serial_no !== '' && $qty !== 1) {
                throw new InvalidArgumentException('A return for one specific serial must have quantity 1.');
            }

            $pdo->beginTransaction();
            $supplierCheck = $pdo->prepare('SELECT id FROM suppliers WHERE id = ? FOR UPDATE');
            $supplierCheck->execute([$supplier_id]);
            if (!$supplierCheck->fetchColumn()) {
                throw new RuntimeException('The selected supplier does not exist.');
            }

            $serialIds = [];
            if ($serial_no !== '') {
                $serialStatement = $pdo->prepare("SELECT id FROM product_serials WHERE product_id = ? AND serial_number = ? AND status = 'in_stock' FOR UPDATE");
                $serialStatement->execute([$product_id, $serial_no]);
                $serialId = $serialStatement->fetchColumn();
                if (!$serialId) {
                    throw new RuntimeException('That serial is not an in-stock unit of the selected product.');
                }
                $serialIds[] = (int)$serialId;
            } else {
                $serialStatement = $pdo->prepare("SELECT id FROM product_serials WHERE product_id = ? AND status = 'in_stock' ORDER BY id LIMIT {$qty} FOR UPDATE");
                $serialStatement->execute([$product_id]);
                $serialIds = array_map('intval', $serialStatement->fetchAll(PDO::FETCH_COLUMN));
                if (count($serialIds) !== $qty) {
                    throw new RuntimeException('There are not enough in-stock units to process this return.');
                }
            }

            $returnStatement = $pdo->prepare("
                INSERT INTO purchase_returns (return_no, supplier_id, product_id, serial_number, quantity, refund_amount, reason, refund_type, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $returnStatement->execute([$return_no, $supplier_id, $product_id, $serial_no ?: null, $qty, $refund_amount, $reason, $refund_type]);

            $placeholders = implode(',', array_fill(0, count($serialIds), '?'));
            $serialUpdate = $pdo->prepare("UPDATE product_serials SET status = 'returned', notes = ? WHERE id IN ({$placeholders})");
            $serialUpdate->execute(array_merge(['Purchase return ' . $return_no . ': ' . $reason], $serialIds));

            if ($refund_amount > 0) {
                $supplierStatement = $pdo->prepare('UPDATE suppliers SET balance_due = GREATEST(0, balance_due - ?) WHERE id = ?');
                $supplierStatement->execute([$refund_amount, $supplier_id]);
            }

            $pdo->commit();
            $msg = "Purchase Return #$return_no processed ($refund_type: " . number_format($refund_amount, 2) . ')!';
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = 'Error creating return: ' . safe_error_message($exception);
            $msg_type = 'error';
        }
    }

    // 7. Record Supplier Payment ──────────────────────────────────────────────
    if ($action === 'record_payment' && $pdo) {
        try {
            $supplier_id    = (int)($_POST['supplier_id'] ?? 0);
            $po_id          = (int)($_POST['po_id'] ?? 0); // optional — 0 = general payment
            $pay_date       = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
            $amount         = round((float)($_POST['amount'] ?? 0), 2);
            $method         = $_POST['payment_method'] ?? 'Cash';
            $ref_no         = trim($_POST['reference_no'] ?? '');
            $notes          = trim($_POST['notes'] ?? '');
            $valid_methods  = ['Cash','Bank Transfer','Cheque','Online','Credit Card','Other'];

            if ($supplier_id < 1)           throw new InvalidArgumentException('Please select a valid supplier.');
            if ($amount <= 0)               throw new InvalidArgumentException('Payment amount must be greater than zero.');
            if (!in_array($method, $valid_methods, true)) throw new InvalidArgumentException('Invalid payment method.');

            $date_parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $pay_date);
            if (!$date_parsed) throw new InvalidArgumentException('Invalid payment date.');

            $pdo->beginTransaction();

            // Validate supplier exists and lock row
            $supp_row = $pdo->prepare('SELECT id, name, balance_due FROM suppliers WHERE id = ? FOR UPDATE');
            $supp_row->execute([$supplier_id]);
            $supp = $supp_row->fetch(PDO::FETCH_ASSOC);
            if (!$supp) throw new RuntimeException('Supplier not found.');

            // Validate PO if linked
            $po_row = null;
            if ($po_id > 0) {
                $po_stmt = $pdo->prepare('SELECT id, invoice_no, total_amount, amount_paid, payment_status FROM purchases WHERE id = ? AND supplier_id = ? FOR UPDATE');
                $po_stmt->execute([$po_id, $supplier_id]);
                $po_row = $po_stmt->fetch(PDO::FETCH_ASSOC);
                if (!$po_row) throw new RuntimeException('This PO does not belong to the selected supplier.');
                $po_balance = round((float)$po_row['total_amount'] - (float)$po_row['amount_paid'], 2);
                if ($amount > $po_balance + 0.01) {
                    throw new InvalidArgumentException("Payment amount ($amount) exceeds PO balance due (" . number_format($po_balance, 2) . "). Reduce the amount or choose 'General Payment (All POs)'.");
                }
            }

            // Insert payment record
            $ins = $pdo->prepare('
                INSERT INTO supplier_payments (supplier_id, purchase_id, payment_date, amount, payment_method, reference_no, notes, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([
                $supplier_id,
                $po_id > 0 ? $po_id : null,
                $pay_date,
                $amount,
                $method,
                $ref_no ?: null,
                $notes ?: null,
                $user['id'] ?? null
            ]);

            // Reduce supplier balance_due
            $upd_supp = $pdo->prepare('UPDATE suppliers SET balance_due = GREATEST(0, balance_due - ?) WHERE id = ?');
            $upd_supp->execute([$amount, $supplier_id]);

            // Update PO amount_paid & payment_status if linked
            if ($po_id > 0 && $po_row) {
                $new_paid   = round((float)$po_row['amount_paid'] + $amount, 2);
                $new_status = ($new_paid >= (float)$po_row['total_amount'] - 0.01) ? 'Paid' : 'Partial';
                $upd_po = $pdo->prepare('UPDATE purchases SET amount_paid = ?, payment_status = ? WHERE id = ?');
                $upd_po->execute([$new_paid, $new_status, $po_id]);
            }

            // Activity log
            try {
                $log = $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, table_name, record_id, details) VALUES (?, 'Record Payment', 'Purchasing', 'supplier_payments', ?, ?)");
                $log->execute([$user['id'] ?? null, $pdo->lastInsertId(), "Paid " . number_format($amount, 2) . " to {$supp['name']}" . ($po_id > 0 ? " for PO #{$po_row['invoice_no']}" : " (General)") . " via $method"]);
            } catch (Exception $le) {}

            $pdo->commit();
            $msg = "Payment of " . htmlspecialchars($currency_symbol) . " " . number_format($amount, 2) . " recorded for {$supp['name']}" . ($po_id > 0 ? " (PO: {$po_row['invoice_no']})" : " (General Payment)") . " via $method.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = 'Error recording payment: ' . safe_error_message($e);
            $msg_type = 'error';
        }
    }

}

require_once '../includes/header.php';

// Active Tab Selector
$tab = $_GET['tab'] ?? 'pos';
$selected_po_id = (int)($_GET['po_id'] ?? 0);

// Fetch Suppliers, Products, and POs with fulfillment tracking
$suppliers = [];
$products = [];
$purchase_orders = [];
$purchase_returns = [];
$supplier_payments_list = [];
$total_po_count = 0;
$pending_po_count = 0;
$total_ap_due = 0.0;
$total_paid_this_month = 0.0;

if ($pdo) {
    try {
        $suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $products = $pdo->query("SELECT id, name, product_code, cost_price, reorder_level FROM products WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch POs with supplier details & fulfillment aggregates
        $stmt_po = $pdo->query("
            SELECT p.*, s.name as supplier_name, s.payment_terms as supp_terms, s.phone as supp_phone,
                   COUNT(pi.id) as line_items_count,
                   COALESCE(SUM(pi.quantity), 0) as total_ordered_units,
                   COALESCE(SUM(pi.received_quantity), 0) as total_received_units
            FROM purchases p
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
            GROUP BY p.id
            ORDER BY p.id DESC
            LIMIT 100
        ");
        $purchase_orders = $stmt_po->fetchAll(PDO::FETCH_ASSOC);
        $total_po_count = count($purchase_orders);

        foreach ($purchase_orders as $po) {
            $st = $po['status'] ?? 'Draft';
            if (in_array($st, ['Draft', 'Submitted', 'Approved', 'Sent to Supplier', 'Partially Received'])) {
                $pending_po_count++;
            }
        }

        $stmt_ap = $pdo->query("SELECT COALESCE(SUM(balance_due), 0) FROM suppliers");
        $total_ap_due = (float)$stmt_ap->fetchColumn();

        // Fetch currency symbol from settings
        try {
            $stmt_curr = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol' LIMIT 1");
            $curr_val = $stmt_curr->fetchColumn();
            if ($curr_val !== false && $curr_val !== '') {
                $currency_symbol = $curr_val;
            }
        } catch (Exception $e_curr) {}

        // Fetch Returns
        $stmt_ret_list = $pdo->query("
            SELECT pr.*, s.name as supplier_name, p.name as product_name, p.product_code
            FROM purchase_returns pr
            LEFT JOIN suppliers s ON pr.supplier_id = s.id
            LEFT JOIN products p ON pr.product_id = p.id
            ORDER BY pr.id DESC
            LIMIT 50
        ");
        $purchase_returns = $stmt_ret_list->fetchAll(PDO::FETCH_ASSOC);

        // Fetch recent supplier payments (for Payments tab)
        $stmt_payments_list = $pdo->query("
            SELECT sp.*,
                   s.name as supplier_name,
                   p.invoice_no as po_number
            FROM supplier_payments sp
            LEFT JOIN suppliers s ON sp.supplier_id = s.id
            LEFT JOIN purchases p ON sp.purchase_id = p.id
            ORDER BY sp.payment_date DESC, sp.id DESC
            LIMIT 100
        ");
        $supplier_payments_list = $stmt_payments_list->fetchAll(PDO::FETCH_ASSOC);

        // Total payments made (this month)
        $total_paid_this_month = (float)$pdo->query("
            SELECT COALESCE(SUM(amount), 0) FROM supplier_payments
            WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())
        ")->fetchColumn();

    } catch (Exception $e) {}
}

?>

<div class="space-y-6 max-w-7xl mx-auto">
    
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-cart-flatbed text-emerald-600"></i>
                <span>Purchasing & Supply Chain Management</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Purchase Orders (PO), Goods Received Notes (GRN), Supplier RMA returns, and AP.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <button onclick="openCreatePOModal()" class="px-5 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs sm:text-sm font-bold transition-all shadow-sm shadow-emerald-500/25 flex items-center gap-2">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Create Purchase Order (PO)</span>
            </button>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="<?php echo $msg_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'; ?> border px-4 py-3 rounded-2xl text-xs sm:text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <i class="fa-solid <?php echo $msg_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-exclamation text-red-600'; ?>"></i>
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200/80">
        <a href="purchases.php?tab=pos" class="px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all whitespace-nowrap <?php echo $tab === 'pos' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <i class="fa-solid fa-file-invoice mr-1.5"></i> Purchase Orders (POs)
        </a>
        <a href="purchases.php?tab=grn" class="px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all whitespace-nowrap <?php echo $tab === 'grn' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <i class="fa-solid fa-boxes-packing mr-1.5"></i> Receive Goods (GRN)
        </a>
        <a href="purchases.php?tab=returns" class="px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all whitespace-nowrap <?php echo $tab === 'returns' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <i class="fa-solid fa-arrow-rotate-left mr-1.5"></i> Purchase Returns (RMA)
        </a>
        <a href="purchases.php?tab=scan" class="px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all whitespace-nowrap <?php echo $tab === 'scan' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <i class="fa-solid fa-barcode mr-1.5"></i> Rapid Barcode Inbound
        </a>
        <a href="purchases.php?tab=payments" class="px-4 py-2.5 rounded-2xl font-bold text-xs sm:text-sm transition-all whitespace-nowrap <?php echo $tab === 'payments' ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <i class="fa-solid fa-money-bill-wave mr-1.5"></i> Supplier Payments
            <?php if ($total_ap_due > 0): ?><span class="ml-1 px-1.5 py-0.5 rounded-full bg-red-100 text-red-700 text-[10px] font-extrabold"><?php echo number_format($total_ap_due, 0); ?> Due</span><?php endif; ?>
        </a>
    </div>

    <!-- KPI Summary Row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-3xl p-5 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold shrink-0">
                <i class="fa-solid fa-file-invoice"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold text-slate-900"><?php echo $pending_po_count; ?> Active</h3>
                <p class="text-xs text-slate-400 font-medium">Pending Delivery / Draft POs</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-5 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold shrink-0">
                <i class="fa-solid fa-truck-ramp-box"></i>
            </div>
            <div>
                <h3 class="text-2xl font-extrabold text-slate-900"><?php echo $total_po_count; ?> Total</h3>
                <p class="text-xs text-slate-400 font-medium">Lifetime Purchase Orders</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-5 shadow-card border border-slate-100/90 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-xl font-bold shrink-0">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <div>
                <h3 class="text-xl font-extrabold text-red-600"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_ap_due, 2); ?></h3>
                <p class="text-xs text-slate-400 font-medium">Total Balance Due to Suppliers</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-5 shadow-card border border-slate-100/90 flex items-center gap-4 cursor-pointer hover:border-emerald-300 transition-all" onclick="window.location='purchases.php?tab=payments'">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold shrink-0">
                <i class="fa-solid fa-money-bill-wave"></i>
            </div>
            <div>
                <h3 class="text-xl font-extrabold text-emerald-700"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($total_paid_this_month, 2); ?></h3>
                <p class="text-xs text-slate-400 font-medium">Paid to Suppliers This Month</p>
            </div>
        </div>
    </div>

    <?php if ($tab === 'pos'): ?>
    <!-- Tab 1: Purchase Orders (PO List) -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Purchase Orders Workflow</h2>
                <p class="text-xs text-slate-400 font-medium">Draft &rarr; Submitted &rarr; Approved &rarr; Sent &rarr; Received</p>
            </div>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input 
                    type="text" 
                    id="poSearch" 
                    onkeyup="filterPOs()" 
                    placeholder="Search PO #, supplier..." 
                    class="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200/80 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 w-64"
                >
            </div>
        </div>

        <div class="overflow-x-auto -mx-6 sm:-mx-7 px-6 sm:px-7">
            <table class="w-full text-left border-collapse" id="poTable">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pr-4 pl-2">PO Number</th>
                        <th class="py-3.5 px-4">Supplier</th>
                        <th class="py-3.5 px-4 text-center">Fulfillment (Received / Ordered)</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4">Total Amount</th>
                        <th class="py-3.5 pl-4 pr-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 text-xs sm:text-sm text-slate-700">
                    <?php foreach ($purchase_orders as $po): 
                        $po_status = $po['status'] ?? 'Draft';
                        $po_num = $po['invoice_no'] ?? ('PO-' . ($po['id'] ?? '1'));
                        $po_supp = $po['supplier_name'] ?? 'Vendor';
                        $po_date = $po['purchase_date'] ?? date('Y-m-d');
                        $po_amt = (float)($po['total_amount'] ?? 0);
                        $po_lines = (int)($po['line_items_count'] ?? 0);
                        $po_id_val = (int)($po['id'] ?? 0);
                        $ord_units = (int)($po['total_ordered_units'] ?? 0);
                        $rec_units = (int)($po['total_received_units'] ?? 0);
                        $pct = $ord_units > 0 ? min(100, round(($rec_units / $ord_units) * 100)) : 0;

                        $status_colors = [
                            'Draft' => 'bg-slate-100 text-slate-700 border-slate-200',
                            'Submitted' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'Approved' => 'bg-blue-50 text-blue-700 border-blue-200',
                            'Sent to Supplier' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                            'Partially Received' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                            'Received' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'Closed' => 'bg-slate-100 text-slate-600 border-slate-200',
                            'Cancelled' => 'bg-red-50 text-red-700 border-red-200'
                        ][$po_status] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                    ?>
                    <tr class="hover:bg-slate-50/60 transition-colors group">
                        
                        <!-- PO Number -->
                        <td class="py-4 pr-4 pl-2 font-mono font-bold text-emerald-700 cursor-pointer" onclick="viewPODetails(<?php echo $po_id_val; ?>)">
                            <span class="hover:underline"><?php echo htmlspecialchars($po_num); ?></span>
                            <span class="block text-[10px] text-slate-400 font-sans font-normal"><?php echo date('M j, Y', strtotime($po_date)); ?></span>
                        </td>

                        <!-- Supplier -->
                        <td class="py-4 px-4 font-bold text-slate-900">
                            <?php echo htmlspecialchars($po_supp); ?>
                            <span class="block text-[11px] text-slate-400 font-normal"><?php echo $po_lines; ?> Line(s)</span>
                        </td>

                        <!-- Fulfillment -->
                        <td class="py-4 px-4 text-center">
                            <div class="max-w-[140px] mx-auto">
                                <div class="flex justify-between text-[11px] font-bold mb-1">
                                    <span class="<?php echo $rec_units >= $ord_units && $ord_units > 0 ? 'text-emerald-600' : 'text-slate-700'; ?>"><?php echo $rec_units; ?> / <?php echo $ord_units; ?> Units</span>
                                    <span class="text-slate-400"><?php echo $pct; ?>%</span>
                                </div>
                                <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: <?php echo $pct; ?>%"></div>
                                </div>
                            </div>
                        </td>

                        <!-- Status Badge -->
                        <td class="py-4 px-4 text-center">
                            <span class="inline-block border text-xs font-bold px-3 py-1 rounded-xl <?php echo $status_colors; ?>">
                                <?php echo htmlspecialchars($po_status); ?>
                            </span>
                        </td>

                        <!-- Total Amount -->
                        <td class="py-4 px-4 font-extrabold text-slate-900">
                            <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($po_amt, 2); ?>
                        </td>

                        <!-- Actions -->
                        <td class="py-4 pl-4 pr-2 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <?php if ($po_status === 'Submitted' && in_array($role, ['Admin', 'Manager'])): ?>
                                    <form method="POST" action="purchases.php" class="inline">
                                        <input type="hidden" name="action" value="approve_po">
                                        <input type="hidden" name="po_id" value="<?php echo $po_id_val; ?>">
                                        <input type="hidden" name="decision" value="Approved">
                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white font-bold text-xs transition-colors">
                                            Approve
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($po_status === 'Approved'): ?>
                                    <form method="POST" action="purchases.php" class="inline">
                                        <input type="hidden" name="action" value="send_po">
                                        <input type="hidden" name="po_id" value="<?php echo $po_id_val; ?>">
                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white font-bold text-xs transition-colors">
                                            Send to Supplier
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (in_array($po_status, ['Sent to Supplier', 'Partially Received', 'Approved'])): ?>
                                    <a href="purchases.php?tab=grn&po_id=<?php echo $po_id_val; ?>" class="px-3 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs transition-colors shadow-sm shadow-emerald-500/20">
                                        Receive GRN
                                    </a>
                                <?php endif; ?>

                                <button onclick="viewPODetails(<?php echo $po_id_val; ?>)" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-500 hover:text-slate-900 hover:bg-slate-50 flex items-center justify-center transition-colors text-xs" title="View & Print PO">
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                                <?php if (!in_array($po_status, ['Received', 'Partially Received']) && in_array($role, ['Admin', 'Manager'])): ?>
                                    <form method="POST" action="purchases.php" onsubmit="return confirm('Delete PO <?php echo addslashes($po_num); ?>?');" class="inline">
                                        <input type="hidden" name="action" value="delete_po">
                                        <input type="hidden" name="po_id" value="<?php echo $po_id_val; ?>">
                                        <button type="submit" class="w-8 h-8 rounded-xl border border-slate-200 text-slate-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center transition-colors text-xs" title="Delete PO">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
    <?php endif; ?>

    <?php if ($tab === 'grn'): ?>
    <!-- Tab 2: Goods Received Note (Receive Stock) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Intake Form -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7">
            <h2 class="text-lg font-bold text-slate-900 mb-2">Receive Shipment (GRN Inbound Intake)</h2>
            <p class="text-xs text-slate-400 mb-6">Select an active purchase order, confirm quantities delivered, and scan all inbound serial numbers.</p>

            <form method="POST" action="purchases.php" onsubmit="return validateGRNForm()" class="space-y-4">
                <input type="hidden" name="action" value="receive_grn">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Purchase Order (PO) *</label>
                    <select name="po_id" id="grnPoSelect" onchange="onGrnPoChanged(this.value)" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="">-- Select Active PO --</option>
                        <?php foreach ($purchase_orders as $po): 
                            $po_disp_num = $po['invoice_no'] ?? ('PO-' . ($po['id'] ?? '1'));
                            $po_disp_supp = $po['supplier_name'] ?? 'Vendor';
                            $po_disp_amt = (float)($po['total_amount'] ?? 0);
                            $po_disp_st = $po['status'] ?? 'Draft';
                            $po_id_num = (int)($po['id'] ?? 1);
                            $is_sel = ($selected_po_id === $po_id_num) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $po_id_num; ?>" <?php echo $is_sel; ?>>
                                <?php echo htmlspecialchars($po_disp_num . ' - ' . $po_disp_supp . ' (' . $currency_symbol . ' ' . number_format($po_disp_amt, 2) . ') [' . $po_disp_st . ']'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Product Receiving *</label>
                    <select name="product_id" id="grnProductSelect" onchange="onGrnProductChanged()" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="">-- Select Product From PO --</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">Quantity Delivered *</label>
                            <span id="grnMaxBadge" class="text-[11px] font-bold text-emerald-600"></span>
                        </div>
                        <input type="number" name="received_qty" id="grnQty" value="1" min="1" oninput="updateSerialCounter()" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    </div>
                    <div class="flex items-center pt-6">
                        <input type="checkbox" name="is_non_serialized" id="nonSerializedToggle" onchange="toggleSerializedMode()" value="1" class="w-4 h-4 text-emerald-600 rounded">
                        <label for="nonSerializedToggle" class="ml-2 text-xs font-bold text-slate-700">Non-Serialized / Bulk Accessories (Cables, parts)</label>
                    </div>
                </div>

                <div id="serialInputContainer">
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">Unique Serial Numbers / IMEIs (One per line or comma-separated) *</label>
                        <span id="serialLiveCounter" class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600">Scanned: 0 / 1</span>
                    </div>
                    <textarea name="serials_list" id="grnSerialsList" rows="4" oninput="updateSerialCounter()" placeholder="Scan or type unique serial numbers (one per line):&#10;SN-8921389-01&#10;SN-8921389-02" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
                    
                    <div id="serialFeedback" class="mt-1.5 text-xs font-semibold"></div>
                </div>

                <button type="submit" class="px-6 py-3 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm rounded-2xl transition-all shadow-sm shadow-emerald-500/25">
                    Confirm GRN & Update Inventory
                </button>
            </form>
        </div>

        <!-- PO Ordered Line Items Guide -->
        <div class="lg:col-span-1 bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7">
            <h3 class="font-bold text-slate-900 text-sm mb-1">PO Ordered Items & Fulfillment</h3>
            <p class="text-xs text-slate-400 mb-4" id="grnGuideSubtitle">Select a PO to view ordered products & remaining units.</p>

            <div id="grnGuideContainer" class="space-y-2 text-xs">
                <div class="p-4 bg-slate-50 rounded-2xl text-center text-slate-400 italic">
                    No PO selected.
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

    <?php if ($tab === 'returns'): ?>
    <!-- Tab 3: Purchase Returns (RMA) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form -->
        <div class="lg:col-span-1 bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7">
            <h2 class="text-lg font-bold text-slate-900 mb-2">Create Return (RMA)</h2>
            <p class="text-xs text-slate-400 mb-6">Return damaged, defective, or overstocked items back to vendor.</p>

            <form method="POST" action="purchases.php" class="space-y-4">
                <input type="hidden" name="action" value="create_return">

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Supplier *</label>
                    <select name="supplier_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <?php foreach ($suppliers as $sp): 
                            $sp_id = $sp['id'] ?? 1;
                            $sp_name = $sp['name'] ?? 'Supplier';
                        ?>
                            <option value="<?php echo $sp_id; ?>"><?php echo htmlspecialchars($sp_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Product Returning *</label>
                    <select name="product_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <?php foreach ($products as $pr): 
                            $pr_id = $pr['id'] ?? 1;
                            $pr_name = $pr['name'] ?? 'Product';
                            $pr_code = $pr['product_code'] ?? 'SKU';
                        ?>
                            <option value="<?php echo $pr_id; ?>"><?php echo htmlspecialchars($pr_name . ' (' . $pr_code . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Serial Number (If tracked)</label>
                    <input type="text" name="serial_number" placeholder="e.g. SN-DXPS15-001" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Refund / Credit Amount (<?php echo htmlspecialchars($currency_symbol); ?>)</label>
                    <input type="number" step="0.01" name="refund_amount" value="0.00" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Return Reason</label>
                    <select name="reason" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="Defective on arrival (DOA)">Defective on arrival (DOA)</option>
                        <option value="Wrong item shipped">Wrong item shipped by vendor</option>
                        <option value="Overstock / Stock return">Overstock / Excess stock</option>
                        <option value="Quality defect in batch">Quality defect in batch</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Settlement Type</label>
                    <select name="refund_type" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <option value="Credit Note">Vendor Credit Note (Deduct AP)</option>
                        <option value="Replacement">Replacement Unit</option>
                        <option value="Cash Refund">Cash / Bank Refund</option>
                    </select>
                </div>

                <button type="submit" class="w-full py-3 bg-red-600 hover:bg-red-700 text-white font-bold text-xs sm:text-sm rounded-2xl transition-all shadow-sm shadow-red-600/25">
                    Process Purchase Return
                </button>
            </form>
        </div>

        <!-- History Table -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7 overflow-hidden">
            <h2 class="text-lg font-bold text-slate-900 mb-2">Purchase Returns (RMA) History</h2>
            <p class="text-xs text-slate-400 mb-6">Recent items returned to suppliers with credit note details.</p>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                            <th class="py-3 pr-3">Return #</th>
                            <th class="py-3 px-3">Supplier</th>
                            <th class="py-3 px-3">Product / Serial</th>
                            <th class="py-3 px-3">Reason</th>
                            <th class="py-3 px-3">Settlement</th>
                            <th class="py-3 pl-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50 text-xs text-slate-700">
                        <?php if (!empty($purchase_returns)): ?>
                            <?php foreach ($purchase_returns as $ret): ?>
                            <tr>
                                <td class="py-3.5 pr-3 font-mono font-bold text-red-600"><?php echo htmlspecialchars($ret['return_no']); ?></td>
                                <td class="py-3.5 px-3 font-bold text-slate-900"><?php echo htmlspecialchars($ret['supplier_name'] ?? 'Vendor'); ?></td>
                                <td class="py-3.5 px-3">
                                    <span class="block font-medium"><?php echo htmlspecialchars($ret['product_name'] ?? 'Item'); ?></span>
                                    <?php if (!empty($ret['serial_number'])): ?>
                                        <span class="block font-mono text-[10px] text-slate-400"><?php echo htmlspecialchars($ret['serial_number']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-3 text-slate-500"><?php echo htmlspecialchars($ret['reason'] ?? 'Defective'); ?></td>
                                <td class="py-3.5 px-3">
                                    <span class="inline-block px-2.5 py-0.5 rounded-lg bg-slate-100 text-slate-700 font-bold text-[10px]">
                                        <?php echo htmlspecialchars($ret['refund_type']); ?>
                                    </span>
                                </td>
                                <td class="py-3.5 pl-3 text-right font-extrabold text-red-600"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format((float)($ret['refund_amount'] ?? 0), 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-6 text-center text-slate-400 italic">No purchase returns recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'scan'): ?>
    <!-- Tab 4: Rapid Continuous Barcode & Serial Scanner -->
    <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7">
        <h2 class="text-lg font-bold text-slate-900 mb-2">Rapid Serial Number Inbound Scanner</h2>
        <p class="text-xs text-slate-400 mb-6">Scan product barcode once, then rapidly scan unique serial numbers into stock.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Step 1 -->
            <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80">
                <h3 class="font-bold text-slate-800 text-sm mb-3"><span class="bg-emerald-500 text-white w-5 h-5 rounded-full inline-flex items-center justify-center text-xs mr-2">1</span> Scan Product Barcode</h3>
                <form id="productSearchForm" onsubmit="lookupProduct(event)">
                    <div class="relative">
                        <i class="fa-solid fa-box-open absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="productBarcode" class="w-full pl-11 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm uppercase focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="Scan SKU, EAN or UPC..." autofocus required>
                    </div>
                </form>

                <div id="activeProductDisplay" class="mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl hidden">
                    <span class="text-[10px] text-emerald-600 font-bold uppercase tracking-wider block mb-1">Active Product</span>
                    <h4 id="activeProductName" class="font-bold text-slate-900 text-base"></h4>
                    <p id="activeProductCode" class="text-xs text-slate-500 font-mono"></p>
                    <input type="hidden" id="activeProductId" value="">
                </div>
            </div>

            <!-- Step 2 -->
            <div class="p-6 rounded-2xl bg-slate-50 border border-slate-200/80 opacity-50" id="step2Box">
                <h3 class="font-bold text-slate-800 text-sm mb-3"><span class="bg-emerald-500 text-white w-5 h-5 rounded-full inline-flex items-center justify-center text-xs mr-2">2</span> Rapidly Scan Serial Numbers</h3>
                <form id="serialEntryForm" onsubmit="addSerial(event)">
                    <div class="relative">
                        <i class="fa-solid fa-barcode absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="serialInput" disabled class="w-full pl-11 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-xs sm:text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" placeholder="Scan Serial / IMEI Number...">
                    </div>
                </form>

                <div class="mt-4">
                    <span class="text-xs font-bold text-slate-600 block mb-2">Scanned in this session (<span id="scanCount">0</span>):</span>
                    <div id="scannedLog" class="max-h-36 overflow-y-auto space-y-1 text-xs font-mono text-slate-700 bg-white p-2 rounded-xl border border-slate-200">
                        <span class="text-slate-400 italic">No serials scanned yet.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'payments'): ?>
    <!-- Tab 5: Supplier Payments ──────────────────────────────────────── -->
    <div class="space-y-6">

        <!-- Record Payment Form -->
        <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7">
            <div class="flex items-center gap-3 mb-5 pb-4 border-b border-slate-100">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Record Supplier Payment</h2>
                    <p class="text-xs text-slate-400">Log a payment made to a supplier — can be linked to a specific PO or recorded as a general payment</p>
                </div>
            </div>

            <form method="POST" action="purchases.php?tab=payments" class="space-y-5" onsubmit="return validatePaymentForm()">
                <input type="hidden" name="action" value="record_payment">

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Supplier -->
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            <i class="fa-solid fa-truck-field text-emerald-500 mr-1"></i> Supplier *
                        </label>
                        <select name="supplier_id" id="paySupplier" required onchange="loadPOsForPayment(this.value)"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                            <option value="">— Select Supplier —</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo $s['id']; ?>" data-balance="<?php echo $s['balance_due']; ?>">
                                    <?php echo htmlspecialchars($s['name']); ?>
                                    <?php if ((float)$s['balance_due'] > 0): ?>
                                        — Due: <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format((float)$s['balance_due'], 2); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Linked PO (optional) -->
                    <div class="sm:col-span-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            <i class="fa-solid fa-file-invoice text-blue-500 mr-1"></i> Link to PO (Optional)
                        </label>
                        <select name="po_id" id="payPOSelect"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                            <option value="0">— General Payment (All POs) —</option>
                        </select>
                        <p class="text-[10px] text-slate-400 mt-1">If left as General, payment reduces total supplier balance</p>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            <i class="fa-solid fa-coins text-amber-500 mr-1"></i> Amount (<?php echo htmlspecialchars($currency_symbol); ?>) *
                        </label>
                        <input type="number" name="amount" id="payAmount" step="0.01" min="0.01" required placeholder="0.00"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-sm font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                        <p id="payBalanceHint" class="text-[10px] text-slate-400 mt-1 hidden"></p>
                    </div>

                    <!-- Payment Date -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            <i class="fa-solid fa-calendar text-slate-400 mr-1"></i> Payment Date *
                        </label>
                        <input type="date" name="payment_date" required value="<?php echo date('Y-m-d'); ?>"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            <i class="fa-solid fa-credit-card text-purple-500 mr-1"></i> Payment Method *
                        </label>
                        <select name="payment_method" required
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                            <option value="Cash">💵 Cash</option>
                            <option value="Bank Transfer">🏦 Bank Transfer</option>
                            <option value="Cheque">📄 Cheque</option>
                            <option value="Online">🌐 Online / Mobile Payment</option>
                            <option value="Credit Card">💳 Credit Card</option>
                            <option value="Other">📎 Other</option>
                        </select>
                    </div>

                    <!-- Reference No -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            <i class="fa-solid fa-hashtag text-slate-400 mr-1"></i> Reference / Cheque No
                        </label>
                        <input type="text" name="reference_no" placeholder="Bank ref, cheque no, transaction ID..."
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    </div>

                    <!-- Notes (full width) -->
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Notes (Optional)</label>
                        <textarea name="notes" rows="2" placeholder="Additional payment notes or remarks..."
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                    <div id="paymentSummaryBox" class="hidden flex-1 p-3 bg-blue-50 border border-blue-100 rounded-2xl text-xs text-blue-800 font-semibold">
                        <i class="fa-solid fa-circle-info mr-1 text-blue-500"></i>
                        <span id="paymentSummaryText"></span>
                    </div>
                    <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25 flex items-center gap-2">
                        <i class="fa-solid fa-money-bill-wave"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>

        <!-- Per-Supplier Balance Overview -->
        <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7">
            <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-chart-bar text-emerald-600"></i> Supplier Balance Summary
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                            <th class="py-3 pr-4">Supplier</th>
                            <th class="py-3 pr-4">Contact</th>
                            <th class="py-3 pr-4 text-right">Total PO Value</th>
                            <th class="py-3 pr-4 text-right">Balance Due</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php
                        $has_balance = false;
                        foreach ($suppliers as $sv):
                            $bal = (float)($sv['balance_due'] ?? 0);
                            if ($bal <= 0) continue;
                            $has_balance = true;
                        ?>
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-4 pr-4">
                                <span class="font-bold text-slate-900 block"><?php echo htmlspecialchars($sv['name']); ?></span>
                                <span class="text-[10px] text-slate-400"><?php echo htmlspecialchars($sv['payment_terms'] ?? 'N/A'); ?></span>
                            </td>
                            <td class="py-4 pr-4 text-slate-500"><?php echo htmlspecialchars($sv['phone'] ?? '—'); ?></td>
                            <td class="py-4 pr-4 text-right font-semibold text-slate-700">
                                <?php
                                // Get total PO value for this supplier
                                $sv_po_total = 0;
                                if ($pdo) {
                                    try {
                                        $svt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM purchases WHERE supplier_id=?");
                                        $svt->execute([$sv['id']]);
                                        $sv_po_total = (float)$svt->fetchColumn();
                                    } catch (Exception $e) {}
                                }
                                echo htmlspecialchars($currency_symbol) . ' ' . number_format($sv_po_total, 2);
                                ?>
                            </td>
                            <td class="py-4 pr-4 text-right">
                                <span class="font-extrabold <?php echo $bal > 0 ? 'text-red-600' : 'text-emerald-600'; ?>">
                                    <?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format($bal, 2); ?>
                                </span>
                            </td>
                            <td class="py-4 text-center">
                                <?php if ($bal > 0): ?>
                                    <span class="px-2.5 py-1 rounded-xl bg-red-50 text-red-700 border border-red-200 text-[10px] font-bold">Unpaid</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">Settled</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 text-right">
                                <button onclick="prefillPayment(<?php echo $sv['id']; ?>, '<?php echo addslashes(htmlspecialchars($sv['name'])); ?>', <?php echo $bal; ?>)"
                                    class="px-3 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-[10px] font-bold transition-all">
                                    <i class="fa-solid fa-money-bill-wave mr-1"></i> Pay Now
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$has_balance): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                <i class="fa-solid fa-circle-check text-emerald-400 text-2xl mb-2 block"></i>
                                All supplier balances are settled! No outstanding payments.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment History Table -->
        <div class="bg-white rounded-3xl shadow-card border border-slate-100/90 p-6 sm:p-7">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-blue-600"></i> Payment History
                </h2>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="paymentSearch" onkeyup="filterPayments()"
                        placeholder="Search payments..."
                        class="pl-8 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 w-48">
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs" id="paymentsTable">
                    <thead>
                        <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                            <th class="py-3 pr-4">Date</th>
                            <th class="py-3 pr-4">Supplier</th>
                            <th class="py-3 pr-4">Linked PO</th>
                            <th class="py-3 pr-4">Method</th>
                            <th class="py-3 pr-4">Reference</th>
                            <th class="py-3 pr-4 text-right">Amount</th>
                            <th class="py-3">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (!empty($supplier_payments_list)): ?>
                            <?php foreach ($supplier_payments_list as $pay): ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3.5 pr-4 font-semibold text-slate-700"><?php echo date('M j, Y', strtotime($pay['payment_date'])); ?></td>
                                <td class="py-3.5 pr-4 font-bold text-slate-900"><?php echo htmlspecialchars($pay['supplier_name'] ?? '—'); ?></td>
                                <td class="py-3.5 pr-4">
                                    <?php if (!empty($pay['po_number'])): ?>
                                        <span class="px-2 py-0.5 rounded-lg bg-blue-50 text-blue-700 border border-blue-100 font-mono text-[10px] font-bold"><?php echo htmlspecialchars($pay['po_number']); ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">General</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 pr-4">
                                    <?php
                                    $method_icons = ['Cash' => '💵', 'Bank Transfer' => '🏦', 'Cheque' => '📄', 'Online' => '🌐', 'Credit Card' => '💳', 'Other' => '📎'];
                                    $icon = $method_icons[$pay['payment_method']] ?? '💰';
                                    ?>
                                    <span class="font-semibold text-slate-700"><?php echo $icon; ?> <?php echo htmlspecialchars($pay['payment_method']); ?></span>
                                </td>
                                <td class="py-3.5 pr-4 font-mono text-[11px] text-slate-500"><?php echo htmlspecialchars($pay['reference_no'] ?? '—'); ?></td>
                                <td class="py-3.5 pr-4 text-right font-extrabold text-emerald-700"><?php echo htmlspecialchars($currency_symbol); ?> <?php echo number_format((float)$pay['amount'], 2); ?></td>
                                <td class="py-3.5 text-slate-400 text-[11px] max-w-[180px] truncate"><?php echo htmlspecialchars($pay['notes'] ?? '—'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    <i class="fa-solid fa-money-bill-wave text-2xl mb-2 block text-slate-300"></i>
                                    No payments recorded yet. Use the form above to record your first supplier payment.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: Create Purchase Order -->
<div id="createPOModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-2xl p-7 relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeCreatePOModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-3 mb-5 pb-3 border-b border-slate-100">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                <i class="fa-solid fa-file-invoice"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-900">Create Purchase Order (PO)</h3>
                <p class="text-xs text-slate-400">Order inventory from verified suppliers</p>
            </div>
        </div>

        <form method="POST" action="purchases.php" onsubmit="return validatePOForm()" class="space-y-4">
            <input type="hidden" name="action" value="create_po">
            <input type="hidden" name="items_json" id="poItemsJson" value="[]">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Supplier *</label>
                    <select name="supplier_id" id="poSupplier" required class="w-full px-4 py-2.5 bg-white border border-slate-200/80 rounded-2xl text-xs sm:text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500" style="color:#1e293b;background-color:#fff;">
                        <option value="" disabled selected>-- Select Supplier --</option>
                        <?php if (empty($suppliers)): ?>
                            <option value="" disabled>No suppliers found. Add a supplier first.</option>
                        <?php else: ?>
                        <?php foreach ($suppliers as $sp): 
                            $sp_id = $sp['id'] ?? 1;
                            $sp_name = $sp['name'] ?? 'Supplier';
                            $sp_terms = $sp['payment_terms'] ?? 'Net 30';
                        ?>
                            <option value="<?php echo $sp_id; ?>" style="color:#1e293b;background:#fff;"><?php echo htmlspecialchars($sp_name . ' (' . $sp_terms . ')'); ?></option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Expected Delivery Date</label>
                    <input type="date" name="expected_delivery_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                </div>
            </div>

            <!-- Add Line Item Builder -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/70">
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider block mb-2">Add Product Line Item</span>
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                    <div class="sm:col-span-6">
                        <select id="lineProduct" onchange="onProductSelectChange()" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-800">
                            <?php foreach ($products as $pr): 
                                $pr_id = $pr['id'] ?? 1;
                                $pr_name = $pr['name'] ?? 'Product';
                                $pr_cost = (float)($pr['cost_price'] ?? 0);
                            ?>
                                <option value="<?php echo $pr_id; ?>" data-name="<?php echo htmlspecialchars($pr_name); ?>" data-cost="<?php echo $pr_cost; ?>">
                                    <?php echo htmlspecialchars($pr_name . ' (' . $currency_symbol . ' ' . number_format($pr_cost, 2) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <input type="number" id="lineQty" value="5" min="1" placeholder="Qty" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                    </div>
                    <div class="sm:col-span-2">
                        <input type="number" step="0.01" id="lineCost" value="0.00" placeholder="Cost (<?php echo htmlspecialchars($currency_symbol); ?>)" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="button" onclick="addLineItem()" class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-colors">
                            + Add
                        </button>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="mt-3 bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <table class="w-full text-left text-xs" id="poItemsTable">
                        <thead class="bg-slate-100/80 text-slate-500 font-bold border-b border-slate-200">
                            <tr>
                                <th class="p-2">Product</th>
                                <th class="p-2 text-center">Qty</th>
                                <th class="p-2 text-right">Unit Cost</th>
                                <th class="p-2 text-right">Subtotal</th>
                                <th class="p-2 text-center"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr id="emptyLineRow">
                                <td colspan="5" class="p-4 text-center text-slate-400 italic">No line items added yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Financial adjustments -->
                <div class="grid grid-cols-2 gap-3 mt-3 pt-3 border-t border-slate-200/80">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Shipping / Freight (<?php echo htmlspecialchars($currency_symbol); ?>)</label>
                        <input type="number" step="0.01" name="shipping_cost" id="poShippingCost" value="0.00" oninput="recalcPOTotal()" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1">Tax Rate (%)</label>
                        <input type="number" step="0.01" name="tax_rate" id="poTaxRate" value="0.00" oninput="recalcPOTotal()" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mt-2 mb-1">Notes / Instructions</label>
                    <textarea name="notes" rows="2" placeholder="Special delivery instructions or order remarks..." class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800"></textarea>
                </div>

                <div class="flex justify-between items-center mt-3 pt-2 border-t border-slate-200">
                    <span class="text-xs font-bold text-slate-700">Estimated Total Amount:</span>
                    <span class="text-lg font-black text-emerald-700" id="poTotalAmountDisplay"><?php echo htmlspecialchars($currency_symbol); ?> 0.00</span>
                </div>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeCreatePOModal()" class="px-5 py-2.5 rounded-2xl border border-slate-200 text-slate-700 font-bold text-xs sm:text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-2xl bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-xs sm:text-sm transition-all shadow-sm shadow-emerald-500/25">
                    Save & Submit PO
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: PO Details & Invoice Viewer -->
<div id="viewPOModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-floating border border-slate-100 w-full max-w-2xl p-7 relative max-h-[90vh] overflow-y-auto">
        <button onclick="closeViewPOModal()" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center justify-between mb-5 pb-3 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900" id="viewPoNum">Purchase Order</h3>
                    <p class="text-xs text-slate-400" id="viewPoDate"></p>
                </div>
            </div>
            <button onclick="window.print()" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold flex items-center gap-2">
                <i class="fa-solid fa-print"></i> Print PO
            </button>
        </div>

        <div class="space-y-4 text-xs">
            <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl">
                <div>
                    <span class="text-slate-400 block font-semibold uppercase text-[10px]">Supplier Details</span>
                    <p class="font-bold text-slate-900 text-sm mt-0.5" id="viewPoSupplier"></p>
                    <p class="text-slate-600" id="viewPoSupplierContact"></p>
                </div>
                <div>
                    <span class="text-slate-400 block font-semibold uppercase text-[10px]">Order Status</span>
                    <span id="viewPoStatus" class="inline-block mt-1 text-xs font-bold px-3 py-1 rounded-xl bg-slate-100"></span>
                    <p class="text-slate-500 mt-1" id="viewPoTerms"></p>
                </div>
            </div>

            <!-- Items Table with Fulfillment -->
            <div>
                <span class="text-slate-700 font-bold block mb-2">Order Line Items & Delivery Status</span>
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <table class="w-full text-left" id="viewPoItemsTable">
                        <thead class="bg-slate-100 text-slate-500 font-bold border-b border-slate-200">
                            <tr>
                                <th class="p-2.5">Product</th>
                                <th class="p-2.5 text-center">Ordered</th>
                                <th class="p-2.5 text-center">Received</th>
                                <th class="p-2.5 text-center">Remaining</th>
                                <th class="p-2.5 text-right">Unit Cost</th>
                                <th class="p-2.5 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" id="viewPoItemsBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-between items-center pt-3 border-t border-slate-100">
                <span class="text-xs font-bold text-slate-600">Total Purchase Order Value:</span>
                <span class="text-xl font-black text-emerald-700" id="viewPoTotal"></span>
            </div>
        </div>
    </div>
</div>

<script>
let poItems = [];
let currentPoItems = [];

function onProductSelectChange() {
    const sel = document.getElementById('lineProduct');
    const opt = sel.options[sel.selectedIndex];
    if (opt) {
        const cost = parseFloat(opt.getAttribute('data-cost')) || 0;
        document.getElementById('lineCost').value = cost.toFixed(2);
    }
}

function openCreatePOModal() {
    poItems = [];
    onProductSelectChange();
    renderLineItems();
    document.getElementById('createPOModal').classList.remove('hidden');
}
function closeCreatePOModal() {
    document.getElementById('createPOModal').classList.add('hidden');
}

function addLineItem() {
    const sel = document.getElementById('lineProduct');
    const pid = sel.value;
    const opt = sel.options[sel.selectedIndex];
    const name = opt.getAttribute('data-name');
    const cost = parseFloat(document.getElementById('lineCost').value) || 0;
    const qty = parseInt(document.getElementById('lineQty').value) || 1;

    poItems.push({ product_id: pid, name: name, cost: cost, qty: qty });
    renderLineItems();
}

function removeLineItem(idx) {
    poItems.splice(idx, 1);
    renderLineItems();
}

function recalcPOTotal() {
    let subtotal = 0;
    poItems.forEach(item => {
        subtotal += item.qty * item.cost;
    });

    const shipping = parseFloat(document.getElementById('poShippingCost').value) || 0;
    const taxRate = parseFloat(document.getElementById('poTaxRate').value) || 0;
    const taxAmount = (subtotal * taxRate) / 100;
    const grandTotal = subtotal + taxAmount + shipping;

    document.getElementById('poTotalAmountDisplay').textContent = (window.CURRENCY_SYMBOL || "Rs.") + " " + grandTotal.toFixed(2);
}

function renderLineItems() {
    const tbody = document.querySelector('#poItemsTable tbody');
    tbody.innerHTML = '';

    if (poItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-slate-400 italic">No line items added yet.</td></tr>';
    } else {
        poItems.forEach((item, i) => {
            const sub = item.qty * item.cost;
            tbody.innerHTML += `
                <tr>
                    <td class="p-2 font-bold text-slate-800">${item.name}</td>
                    <td class="p-2 text-center font-bold">${item.qty}</td>
                    <td class="p-2 text-right">${window.CURRENCY_SYMBOL || 'Rs.'} ${item.cost.toFixed(2)}</td>
                    <td class="p-2 text-right font-extrabold text-emerald-700">${window.CURRENCY_SYMBOL || 'Rs.'} ${sub.toFixed(2)}</td>
                    <td class="p-2 text-center">
                        <button type="button" onclick="removeLineItem(${i})" class="text-red-500 hover:text-red-700"><i class="fa-solid fa-xmark"></i></button>
                    </td>
                </tr>
            `;
        });
    }

    recalcPOTotal();
    document.getElementById('poItemsJson').value = JSON.stringify(poItems);
}

function validatePOForm() {
    if (poItems.length === 0) {
        alert('Please add at least one line item to the PO.');
        return false;
    }
    return true;
}

function viewPODetails(poId) {
    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'get_po_details');
    fd.append('po_id', poId);

    fetch('purchases.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success && d.po) {
            const po = d.po;
            document.getElementById('viewPoNum').textContent = po.invoice_no || ('PO #' + po.id);
            document.getElementById('viewPoDate').textContent = 'Date: ' + (po.purchase_date || '');
            document.getElementById('viewPoSupplier').textContent = po.supplier_name || 'Vendor';
            document.getElementById('viewPoSupplierContact').textContent = (po.supp_phone ? 'Phone: ' + po.supp_phone : '') + (po.supp_email ? ' | ' + po.supp_email : '');
            document.getElementById('viewPoStatus').textContent = po.status || 'Draft';
            document.getElementById('viewPoTerms').textContent = 'Payment Terms: ' + (po.supp_terms || 'Net 30');
            document.getElementById('viewPoTotal').textContent = (window.CURRENCY_SYMBOL || "Rs.") + " " + parseFloat(po.total_amount || 0).toFixed(2);

            const tbody = document.getElementById('viewPoItemsBody');
            tbody.innerHTML = '';
            if (d.items && d.items.length > 0) {
                d.items.forEach(it => {
                    const sub = (parseInt(it.quantity) || 1) * (parseFloat(it.unit_cost) || 0);
                    const ord = parseInt(it.quantity) || 0;
                    const rec = parseInt(it.received_qty) || 0;
                    const rem = Math.max(0, ord - rec);

                    tbody.innerHTML += `
                        <tr>
                            <td class="p-2.5 font-semibold text-slate-800">${it.product_name || 'Product'} (${it.product_code || ''})</td>
                            <td class="p-2.5 text-center font-bold">${ord}</td>
                            <td class="p-2.5 text-center font-bold text-emerald-600">${rec}</td>
                            <td class="p-2.5 text-center font-bold ${rem > 0 ? 'text-amber-600' : 'text-slate-400'}">${rem}</td>
                            <td class="p-2.5 text-right">${window.CURRENCY_SYMBOL || 'Rs.'} ${parseFloat(it.unit_cost || 0).toFixed(2)}</td>
                            <td class="p-2.5 text-right font-bold text-slate-900">${window.CURRENCY_SYMBOL || 'Rs.'} ${sub.toFixed(2)}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="p-3 text-center text-slate-400">No line item details found.</td></tr>';
            }

            document.getElementById('viewPOModal').classList.remove('hidden');
        } else {
            alert(d.message || 'Could not load PO details.');
        }
    })
    .catch(err => alert('Error loading PO details.'));
}

function closeViewPOModal() {
    document.getElementById('viewPOModal').classList.add('hidden');
}

// GRN PO Selection & Fulfillment Tracker
function onGrnPoChanged(poId) {
    if (!poId) {
        document.getElementById('grnGuideContainer').innerHTML = '<div class="p-4 bg-slate-50 rounded-2xl text-center text-slate-400 italic">No PO selected.</div>';
        const prodSel = document.getElementById('grnProductSelect');
        prodSel.innerHTML = '<option value="">-- Select Product From PO --</option>';
        return;
    }

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'get_po_details');
    fd.append('po_id', poId);

    fetch('purchases.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        const prodSel = document.getElementById('grnProductSelect');
        prodSel.innerHTML = '<option value="">-- Select Product From PO --</option>';
        currentPoItems = d.items || [];

        if (d.success && currentPoItems.length > 0) {
            document.getElementById('grnGuideSubtitle').textContent = 'Click an item below to load it into the intake form:';
            let guideHtml = '';

            currentPoItems.forEach((it, idx) => {
                const ord = parseInt(it.quantity) || 0;
                const rec = parseInt(it.received_qty) || 0;
                const rem = Math.max(0, ord - rec);
                const pct = ord > 0 ? Math.min(100, Math.round((rec / ord) * 100)) : 0;
                const isComplete = rem === 0;

                // Add to select dropdown
                const opt = document.createElement('option');
                opt.value = it.product_id;
                opt.textContent = `${it.product_name} (Remaining: ${rem} / Ordered: ${ord})`;
                opt.setAttribute('data-rem', rem);
                opt.setAttribute('data-ord', ord);
                opt.setAttribute('data-rec', rec);
                if (isComplete) opt.disabled = true;
                prodSel.appendChild(opt);

                guideHtml += `
                    <div onclick="selectGrnItem(${it.product_id}, ${rem})" class="p-3.5 bg-slate-50 hover:bg-emerald-50/70 border ${isComplete ? 'border-emerald-200 bg-emerald-50/30 opacity-75' : 'border-slate-200/80 hover:border-emerald-400 cursor-pointer'} rounded-2xl transition-all">
                        <div class="flex items-center justify-between mb-1.5">
                            <div>
                                <span class="font-bold text-slate-900 block leading-tight">${it.product_name}</span>
                                <span class="text-[10px] text-slate-400 font-mono">${it.product_code}</span>
                            </div>
                            ${isComplete ? 
                                '<span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 font-bold rounded-lg text-[10px]"><i class="fa-solid fa-check mr-1"></i> Completed</span>' : 
                                `<span class="px-2 py-0.5 bg-amber-100 text-amber-800 font-bold rounded-lg text-[10px]">${rem} Units Left</span>`
                            }
                        </div>
                        <div class="flex justify-between text-[10px] text-slate-500 mb-1">
                            <span>Delivered: <b>${rec} / ${ord}</b></span>
                            <span><b>${pct}%</b></span>
                        </div>
                        <div class="w-full h-1.5 bg-slate-200/80 rounded-full overflow-hidden">
                            <div class="h-full ${isComplete ? 'bg-emerald-500' : 'bg-amber-500'} rounded-full" style="width: ${pct}%"></div>
                        </div>
                    </div>
                `;
            });
            document.getElementById('grnGuideContainer').innerHTML = guideHtml;

            // Auto-select first incomplete item if available
            const firstPending = currentPoItems.find(it => Math.max(0, it.quantity - it.received_qty) > 0);
            if (firstPending) {
                selectGrnItem(firstPending.product_id, Math.max(0, firstPending.quantity - firstPending.received_qty));
            }
        } else {
            document.getElementById('grnGuideContainer').innerHTML = '<div class="p-4 bg-slate-50 rounded-2xl text-center text-slate-400 italic">No line items in this PO.</div>';
        }
    });
}

function selectGrnItem(productId, remQty) {
    if (remQty <= 0) return;
    const prodSel = document.getElementById('grnProductSelect');
    prodSel.value = productId;
    onGrnProductChanged();
}

function onGrnProductChanged() {
    const prodSel = document.getElementById('grnProductSelect');
    const opt = prodSel.options[prodSel.selectedIndex];
    if (opt && opt.value) {
        const rem = parseInt(opt.getAttribute('data-rem')) || 1;
        const ord = parseInt(opt.getAttribute('data-ord')) || 1;
        const rec = parseInt(opt.getAttribute('data-rec')) || 0;

        const qtyInput = document.getElementById('grnQty');
        qtyInput.max = rem;
        qtyInput.value = rem > 0 ? rem : 1;
        document.getElementById('grnMaxBadge').textContent = `Remaining on PO: ${rem} / ${ord} units`;
        updateSerialCounter();
    } else {
        document.getElementById('grnMaxBadge').textContent = '';
    }
}

function toggleSerializedMode() {
    const isNonSerialized = document.getElementById('nonSerializedToggle').checked;
    const serialContainer = document.getElementById('serialInputContainer');
    if (isNonSerialized) {
        serialContainer.classList.add('opacity-40', 'pointer-events-none');
        document.getElementById('serialFeedback').innerHTML = '<span class="text-slate-500 font-normal italic"><i class="fa-solid fa-info-circle mr-1"></i> Bulk/non-serialized items will receive auto-generated batch inventory tags.</span>';
    } else {
        serialContainer.classList.remove('opacity-40', 'pointer-events-none');
        updateSerialCounter();
    }
}

function updateSerialCounter() {
    const isNonSerialized = document.getElementById('nonSerializedToggle').checked;
    if (isNonSerialized) return;

    const reqQty = parseInt(document.getElementById('grnQty').value) || 1;
    const raw = document.getElementById('grnSerialsList').value.trim();
    const serials = raw ? raw.split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0) : [];
    const count = serials.length;

    const counterBadge = document.getElementById('serialLiveCounter');
    const feedback = document.getElementById('serialFeedback');

    // Check duplicates
    const unique = new Set(serials);
    const hasDups = unique.size !== serials.length;

    counterBadge.textContent = `Scanned: ${count} / ${reqQty} Required`;

    if (hasDups) {
        counterBadge.className = 'text-xs font-bold px-2.5 py-0.5 rounded-full bg-red-100 text-red-800';
        feedback.innerHTML = '<span class="text-red-600"><i class="fa-solid fa-circle-exclamation mr-1"></i> Duplicate serial numbers detected in your input! Each serial must be unique.</span>';
    } else if (count === reqQty) {
        counterBadge.className = 'text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800';
        feedback.innerHTML = '<span class="text-emerald-600"><i class="fa-solid fa-circle-check mr-1"></i> Exact match! Ready to confirm intake.</span>';
    } else if (count < reqQty) {
        counterBadge.className = 'text-xs font-bold px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800';
        feedback.innerHTML = `<span class="text-amber-600"><i class="fa-solid fa-clock mr-1"></i> ${reqQty - count} more serial number(s) required.</span>`;
    } else {
        counterBadge.className = 'text-xs font-bold px-2.5 py-0.5 rounded-full bg-red-100 text-red-800';
        feedback.innerHTML = `<span class="text-red-600"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Too many serials! You entered ${count}, but quantity is ${reqQty}.</span>`;
    }
}

function validateGRNForm() {
    const isNonSerialized = document.getElementById('nonSerializedToggle').checked;
    const reqQty = parseInt(document.getElementById('grnQty').value) || 0;
    const prodSel = document.getElementById('grnProductSelect');

    if (!prodSel.value) {
        alert('Please select a product to receive.');
        return false;
    }

    const opt = prodSel.options[prodSel.selectedIndex];
    const rem = parseInt(opt.getAttribute('data-rem')) || 999;
    if (reqQty > rem) {
        alert(`Cannot receive ${reqQty} units. Only ${rem} units remaining on this PO.`);
        return false;
    }

    if (!isNonSerialized) {
        const raw = document.getElementById('grnSerialsList').value.trim();
        const serials = raw ? raw.split(/[\r\n,]+/).map(s => s.trim()).filter(s => s.length > 0) : [];

        if (serials.length === 0) {
            alert(`Serial numbers/IMEIs are required for this device! Please enter or scan ${reqQty} serial numbers.`);
            return false;
        }

        if (serials.length !== reqQty) {
            alert(`Quantity mismatch! You specified ${reqQty} units, but entered ${serials.length} serial numbers.`);
            return false;
        }

        const unique = new Set(serials);
        if (unique.size !== serials.length) {
            alert('Duplicate serial numbers detected in your input list. Please make sure all serials are unique.');
            return false;
        }
    }

    return true;
}

// Auto-trigger GRN guide if selected on load
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('grnPoSelect');
    if (sel && sel.value) {
        onGrnPoChanged(sel.value);
    }
});

// Rapid Barcode Scanner JS
let activeProduct = null;
let scannedCount = 0;

function lookupProduct(e) {
    e.preventDefault();
    const barcode = document.getElementById('productBarcode').value;
    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'lookup_product');
    fd.append('code', barcode);

    fetch('purchases.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            activeProduct = d.product;
            document.getElementById('activeProductName').textContent = d.product.name;
            document.getElementById('activeProductCode').textContent = d.product.product_code;
            document.getElementById('activeProductId').value = d.product.id;
            document.getElementById('activeProductDisplay').classList.remove('hidden');
            
            document.getElementById('step2Box').classList.remove('opacity-50');
            document.getElementById('serialInput').disabled = false;
            document.getElementById('serialInput').focus();
        } else {
            alert(d.message);
        }
    });
}

function addSerial(e) {
    e.preventDefault();
    if (!activeProduct) return;
    const serial = document.getElementById('serialInput').value.trim();
    if (!serial) return;

    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'add_serial');
    fd.append('product_id', activeProduct.id);
    fd.append('serial_number', serial);

    fetch('purchases.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            scannedCount++;
            document.getElementById('scanCount').textContent = scannedCount;
            const log = document.getElementById('scannedLog');
            if (scannedCount === 1) log.innerHTML = '';
            log.innerHTML = `<div class="text-emerald-700 font-bold"><i class="fa-solid fa-check mr-1"></i> ${serial}</div>` + log.innerHTML;
            document.getElementById('serialInput').value = '';
        } else {
            alert(d.message);
        }
    });
}

function filterPOs() {
    const q = document.getElementById('poSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#poTable tbody tr');
    rows.forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ── Supplier Payments JS ────────────────────────────────────────────────────

const supplierPOData = {}; // cache: supplierId → [{id, invoice_no, balance_remaining, total_amount}]

function loadPOsForPayment(supplierId) {
    const poSel  = document.getElementById('payPOSelect');
    const hint   = document.getElementById('payBalanceHint');
    const sumBox = document.getElementById('paymentSummaryBox');
    const sumTxt = document.getElementById('paymentSummaryText');

    poSel.innerHTML = '<option value="0">— General Payment (All POs) —</option>';
    if (!supplierId) return;

    // Show balance hint from the option data-balance
    const opt = document.querySelector('#paySupplier option[value="' + supplierId + '"]');
    const bal = opt ? parseFloat(opt.dataset.balance || 0) : 0;
    if (bal > 0 && hint) {
        hint.textContent = 'Total outstanding balance: ' + (window.CURRENCY_SYMBOL || 'Rs.') + ' ' + bal.toFixed(2);
        hint.classList.remove('hidden');
    } else if (hint) {
        hint.classList.add('hidden');
    }

    // Fetch outstanding POs via AJAX
    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('action', 'get_supplier_payments');
    fd.append('supplier_id', supplierId);

    fetch('purchases.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            const outstandingPOs = d.outstanding_pos || [];
            if (outstandingPOs.length > 0) {
                outstandingPOs.forEach(po => {
                    const bal = parseFloat(po.balance_remaining || 0);
                    if (bal <= 0) return;
                    const opt = document.createElement('option');
                    opt.value = po.id;
                    opt.dataset.balance = bal.toFixed(2);
                    opt.textContent = po.invoice_no + ' — Due: ' + (window.CURRENCY_SYMBOL || 'Rs.') + ' ' + bal.toFixed(2);
                    poSel.appendChild(opt);
                });

                // Show summary
                if (sumBox && sumTxt) {
                    sumTxt.textContent = outstandingPOs.length + ' outstanding PO(s). You can pay against a specific PO or record a general payment.';
                    sumBox.classList.remove('hidden');
                }
            } else {
                if (sumBox && sumTxt) {
                    sumTxt.textContent = 'All POs for this supplier are fully paid.';
                    sumBox.classList.remove('hidden');
                }
            }
        })
        .catch(() => {});
}

// Update amount hint when PO is selected
document.addEventListener('DOMContentLoaded', function() {
    const poSel = document.getElementById('payPOSelect');
    const amtIn = document.getElementById('payAmount');
    const hint  = document.getElementById('payBalanceHint');

    if (poSel) {
        poSel.addEventListener('change', function() {
            const selOpt = poSel.options[poSel.selectedIndex];
            const bal = parseFloat(selOpt?.dataset?.balance || 0);
            if (bal > 0 && hint) {
                hint.textContent = 'PO balance remaining: ' + (window.CURRENCY_SYMBOL || 'Rs.') + ' ' + bal.toFixed(2);
                hint.classList.remove('hidden');
                if (amtIn && !amtIn.value) amtIn.value = bal.toFixed(2);
            } else if (hint && poSel.value === '0') {
                // General payment — show supplier total balance
                const suppOpt = document.querySelector('#paySupplier option:checked');
                const suppBal = parseFloat(suppOpt?.dataset?.balance || 0);
                if (suppBal > 0) {
                    hint.textContent = 'Total supplier balance: ' + (window.CURRENCY_SYMBOL || 'Rs.') + ' ' + suppBal.toFixed(2);
                    hint.classList.remove('hidden');
                    if (amtIn && !amtIn.value) amtIn.value = suppBal.toFixed(2);
                }
            }
        });
    }
});

function prefillPayment(supplierId, supplierName, balanceDue) {
    // Scroll to top & switch to payments tab if not there
    const suppSel = document.getElementById('paySupplier');
    if (!suppSel) {
        window.location.href = 'purchases.php?tab=payments';
        return;
    }
    suppSel.value = supplierId;
    loadPOsForPayment(supplierId);

    const amtIn = document.getElementById('payAmount');
    if (amtIn) amtIn.value = parseFloat(balanceDue).toFixed(2);

    suppSel.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function validatePaymentForm() {
    const supp  = document.getElementById('paySupplier');
    const amt   = document.getElementById('payAmount');
    const poSel = document.getElementById('payPOSelect');

    if (!supp || !supp.value) {
        alert('Please select a supplier.');
        supp && supp.focus();
        return false;
    }
    const amount = parseFloat(amt?.value || 0);
    if (isNaN(amount) || amount <= 0) {
        alert('Please enter a valid payment amount greater than zero.');
        amt && amt.focus();
        return false;
    }
    // Warn if PO is selected and amount > balance
    if (poSel && poSel.value !== '0') {
        const selOpt = poSel.options[poSel.selectedIndex];
        const bal = parseFloat(selOpt?.dataset?.balance || Infinity);
        if (amount > bal + 0.01) {
            return confirm('Warning: Payment amount (' + amount.toFixed(2) + ') exceeds the PO balance (' + bal.toFixed(2) + '). Are you sure you want to proceed?');
        }
    }
    return true;
}

function filterPayments() {
    const q = document.getElementById('paymentSearch')?.value.toLowerCase() || '';
    document.querySelectorAll('#paymentsTable tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
