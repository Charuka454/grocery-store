<?php

@include 'config.php';

session_start();

$user_id = $_SESSION['user_id'];

if(!isset($user_id)){
   header('location:login.php');
   exit;
}

/* -----------------------------
   Helper: add stock back
------------------------------*/
function restock_product(PDO $conn, int $pid, int $qty): void {
   if ($qty > 0) {
      $stmt = $conn->prepare("UPDATE `products` SET quantity = quantity + ? WHERE id = ?");
      $stmt->execute([$qty, $pid]);
   }
}

/* -----------------------------
   Delete one cart row
   (restock then delete)
------------------------------*/
if(isset($_GET['delete'])){
   $delete_id = (int)$_GET['delete'];

   try{
      $conn->beginTransaction();

      // Lock the row so qty can't race
      $get = $conn->prepare("SELECT pid, quantity FROM `cart` WHERE id = ? AND user_id = ? FOR UPDATE");
      $get->execute([$delete_id, $user_id]);
      if ($row = $get->fetch(PDO::FETCH_ASSOC)) {
         restock_product($conn, (int)$row['pid'], (int)$row['quantity']);

         $delete_cart_item = $conn->prepare("DELETE FROM `cart` WHERE id = ?");
         $delete_cart_item->execute([$delete_id]);
      }

      $conn->commit();
   } catch (Exception $e) {
      if ($conn->inTransaction()) $conn->rollBack();
   }

   header('location:cart.php');
   exit;
}

/* -----------------------------
   Delete all for this user
   (restock each, then delete)
------------------------------*/
if(isset($_GET['delete_all'])){
   try{
      $conn->beginTransaction();

      $all = $conn->prepare("SELECT id, pid, quantity FROM `cart` WHERE user_id = ? FOR UPDATE");
      $all->execute([$user_id]);
      while ($r = $all->fetch(PDO::FETCH_ASSOC)) {
         restock_product($conn, (int)$r['pid'], (int)$r['quantity']);
      }

      $delete_cart_item = $conn->prepare("DELETE FROM `cart` WHERE user_id = ?");
      $delete_cart_item->execute([$user_id]);

      $conn->commit();
   } catch (Exception $e) {
      if ($conn->inTransaction()) $conn->rollBack();
   }

   header('location:cart.php');
   exit;
}

/* -----------------------------
   Update cart quantity
   (unchanged by your request)
------------------------------*/
if(isset($_POST['update_qty'])){
   $cart_id = $_POST['cart_id'];
   $p_qty = $_POST['p_qty'];
   $p_qty = filter_var($p_qty, FILTER_SANITIZE_STRING);
   $update_qty = $conn->prepare("UPDATE `cart` SET quantity = ? WHERE id = ?");
   $update_qty->execute([$p_qty, $cart_id]);
   $message[] = 'cart quantity updated';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Shopping Cart — Kandu Pinnawala</title>

   <!-- Tailwind CDN -->
   <script src="https://cdn.tailwindcss.com"></script>
   <script>
      tailwind.config = {
         theme: {
            extend: {
               colors: {
                  primary: '#8B4513',
                  secondary: '#A0522D',
                  accent: '#D2B48C',
                  dark: '#3E2723',
                  wood: '#5D4037'
               },
               boxShadow: {
                  neon: '0 0 20px rgba(139,69,19,.5),0 0 40px rgba(160,82,45,.3),0 0 60px rgba(210,180,140,.2)'
               },
               fontFamily: { gaming: ['Orbitron','monospace'] }
            }
         }
      }
   </script>

   <!-- Font Awesome -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <!-- Google Fonts -->
   <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <!-- Custom CSS -->
   <link rel="stylesheet" href="css/style.css">

   <style>
      *{margin:0;padding:0;box-sizing:border-box}
      body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#1B0F0A 0%,#3E2723 50%,#5D4037 100%);color:#fff;overflow-x:hidden}
      .neon-glow{box-shadow:0 0 20px rgba(139,69,19,.5),0 0 40px rgba(160,82,45,.3),0 0 60px rgba(210,180,140,.2)}
      .glass-effect{background:rgba(255,255,255,.08);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.18)}
      .hover-glow:hover{transform:translateY(-5px);box-shadow:0 10px 25px rgba(139,69,19,.35);transition:all .3s ease}
      .floating-animation{animation:floating 3s ease-in-out infinite}
      @keyframes floating{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
      .gradient-text{background:linear-gradient(45deg,#8B4513,#A0522D,#D2B48C);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
      .hero-bg{background:radial-gradient(circle at 20% 80%, rgba(139,69,19,.35) 0%, transparent 55%),radial-gradient(circle at 80% 20%, rgba(210,180,140,.35) 0%, transparent 55%),radial-gradient(circle at 40% 40%, rgba(160,82,45,.35) 0%, transparent 55%)}
      .text-base{font-size:1.125rem!important}.text-lg{font-size:1.25rem!important}.text-xl{font-size:1.375rem!important}
      p,label,input,button,a,li{font-size:1.12rem}
      .card-sheen{background:radial-gradient(600px 120px at 20% 0%, rgba(210,180,140,.18), transparent 60%)}
   </style>
</head>
<body>

<?php include 'header.php'; ?>

<?php
// Fetch all items first
$grand_total = 0;
$select_cart = $conn->prepare("SELECT * FROM `cart` WHERE user_id = ?");
$select_cart->execute([$user_id]);
$cart_items = $select_cart->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="relative min-h-screen flex items-start justify-center overflow-hidden hero-bg py-16">
  <div class="absolute top-10 left-10 w-96 h-96 bg-gradient-to-br from-[rgba(139,69,19,0.25)] to-[rgba(210,180,140,0.22)] rounded-full blur-3xl floating-animation"></div>
  <div class="absolute bottom-10 right-10 w-80 h-80 bg-gradient-to-tr from-[rgba(210,180,140,0.25)] to-[rgba(160,82,45,0.22)] rounded-full blur-3xl floating-animation" style="animation-delay:1s"></div>

  <div class="container mx-auto px-6 lg:px-12 grid lg:grid-cols-3 gap-8 items-start relative z-10 w-full">
    <div class="lg:col-span-3 text-center mb-6">
      <h2 class="text-4xl lg:text-5xl font-extrabold tracking-tight"><span class="gradient-text">Shopping Cart</span></h2>
      <p class="mt-3 text-lg text-white/80">Review items, update quantities, or proceed to checkout.</p>
    </div>

    <?php if(count($cart_items) > 0): ?>
      <div class="lg:col-span-2 space-y-6">
        <?php foreach($cart_items as $fetch_cart):
              $sub_total = ((float)$fetch_cart['price'] * (int)$fetch_cart['quantity']);
              $grand_total += $sub_total;
        ?>
        <form action="" method="POST" class="group glass-effect neon-glow rounded-3xl overflow-hidden border border-[rgba(210,180,140,0.28)]">
          <div class="px-4 py-3 flex items-center justify-between bg-gradient-to-r from-primary/70 to-accent/60 backdrop-blur">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full glass-effect text-white text-sm font-bold border border-[rgba(255,255,255,0.25)]">
              <i class="fas fa-tag text-accent"></i> Rs <?= number_format((float)$fetch_cart['price'], 2); ?>/-
            </div>
            <div class="flex items-center gap-2">
              <a href="view_page.php?pid=<?= (int)$fetch_cart['pid']; ?>" class="w-10 h-10 glass-effect rounded-full flex items-center justify-center text-white hover-glow border border-[rgba(255,255,255,0.25)]" title="View item"><i class="fas fa-eye"></i></a>
              <a href="cart.php?delete=<?= (int)$fetch_cart['id']; ?>" onclick="return confirm('Delete this from cart?');" class="w-10 h-10 glass-effect rounded-full flex items-center justify-center text-white hover-glow border border-[rgba(255,255,255,0.25)]" title="Remove"><i class="fas fa-times"></i></a>
            </div>
          </div>

          <div class="p-6">
            <div class="flex flex-col sm:flex-row gap-6">
              <div class="w-full sm:w-40 h-40 rounded-2xl overflow-hidden flex-shrink-0 border border-[rgba(210,180,140,0.25)] bg-[rgba(255,255,255,0.06)] card-sheen">
                <img src="uploaded_img/<?= htmlspecialchars($fetch_cart['image']); ?>" alt="<?= htmlspecialchars($fetch_cart['name']); ?>" class="w-full h-full object-cover" onerror="this.src='uploaded_img/placeholder.png'">
              </div>

              <div class="flex-1">
                <h3 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars($fetch_cart['name']); ?></h3>

                <input type="hidden" name="cart_id" value="<?= (int)$fetch_cart['id']; ?>">

                <div class="grid sm:grid-cols-2 gap-4 items-end">
                  <div>
                    <label class="block text-sm font-medium text-white/80 mb-2">Quantity</label>
                    <div class="flex items-center gap-2">
                      <input type="number" min="1" name="p_qty" value="<?= (int)$fetch_cart['quantity']; ?>" class="w-28 px-4 py-3 rounded-xl bg-[rgba(255,255,255,0.08)] text-white border border-[rgba(255,255,255,0.18)] focus:outline-none focus:ring-2 focus:ring-accent/50">
                      <button type="submit" name="update_qty" class="px-4 py-3 rounded-xl font-semibold glass-effect hover-glow">Update</button>
                    </div>
                  </div>
                  <div class="sm:text-right">
                    <p class="text-sm text-white/70 mb-1">Sub Total</p>
                    <p class="text-2xl font-extrabold text-white">Rs <?= number_format($sub_total, 2); ?>/-</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </form>
        <?php endforeach; ?>
      </div>

      <div class="glass-effect neon-glow rounded-3xl overflow-hidden border border-[rgba(210,180,140,0.28)] h-max">
        <div class="px-4 py-3 bg-gradient-to-r from-primary/70 to-accent/60 backdrop-blur">
          <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full glass-effect text-white text-sm font-bold border border-[rgba(255,255,255,0.25)]">
            <i class="fas fa-receipt text-accent"></i> Order Summary
          </div>
        </div>

        <div class="p-6">
          <div class="rounded-2xl bg-[rgba(255,255,255,0.06)] p-4 space-y-2 border border-[rgba(255,255,255,0.12)]">
            <div class="flex items-center justify-between text-white/80"><span>Items</span><span><?= count($cart_items); ?></span></div>
            <div class="flex items-center justify-between text-white/80"><span>Subtotal</span><span>Rs <?= number_format($grand_total, 2); ?>/-</span></div>
            <div class="flex items-center justify-between text-white/80"><span>Shipping</span><span>FREE</span></div>
            <div class="pt-3 border-t border-white/10 flex items-center justify-between"><span class="text-lg font-bold text-white">Grand Total</span><span class="text-lg font-extrabold text-white">Rs <?= number_format($grand_total, 2); ?>/-</span></div>
          </div>

          <div class="mt-6 grid gap-3">
            <a href="shop.php" class="text-center glass-effect rounded-xl py-3 font-semibold text-white hover-glow border border-[rgba(255,255,255,0.25)]">Continue Shopping</a>
            <a href="cart.php?delete_all" class="text-center glass-effect rounded-xl py-3 font-semibold text-accent hover-glow border border-accent/40 <?= ($grand_total > 0)?'':'pointer-events-none opacity-50'; ?>">Delete All</a>
            <a href="checkout.php" class="text-center bg-gradient-to-r from-primary to-accent text-white py-4 rounded-xl font-semibold hover-glow neon-glow <?= ($grand_total > 0)?'':'pointer-events-none opacity-50'; ?>">Proceed to Checkout</a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="lg:col-span-3 text-center py-16 glass-effect rounded-3xl border border-[rgba(255,255,255,0.18)]">
        <i class="fas fa-box-open text-6xl text-white/30 mb-4"></i>
        <p class="text-2xl text-white/80 font-medium">Your cart is empty</p>
        <a href="shop.php" class="mt-6 inline-flex items-center justify-center bg-gradient-to-r from-primary to-accent text-white px-6 py-3 rounded-xl font-semibold hover-glow neon-glow"><i class="fas fa-store mr-2"></i> Shop Now</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'footer.php'; ?>

<script src="js/script.js"></script>

<a href="#" class="ai-chat-widget" title="Need help?">
  <i class="fas fa-comments text-2xl text-dark"></i>
</a>

</body>
</html>
