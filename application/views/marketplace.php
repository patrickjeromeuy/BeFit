<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url('assets/css/marketplace_styles.css')?>">
    <title>BeFit Homepage</title>
</head>
<div class="nav">
      <ul class="items">
        <li><a href="<?php echo base_url('user/marketplace/')?>">Marketplace</a></li>
		<li><a href="<?php echo base_url('user/nutrition/')?>">Nutrition</a></li>
        <li><a href="<?php echo base_url('user/faq/')?>">FAQ</a></li>
        <li><a href="<?php echo base_url('user/aboutus/')?>">About</a></li>
      </ul>
</div>

<body>
    <div class="containbox">
	<?php 
		foreach($records as $row) {
            echo "<div class='box'>";
			echo "<p><a href='".base_url().'user/service/'.$row->services_id."'>".$row->services_title."</a></p>";
            echo "<p>".$row->services_price."</p>";
			echo "<p>".$row->services_description."</p>";
			echo "<p>".$row->services_type."</p>";
			echo "<p>".$row->services_time."</p>";
			echo "<p>".$row->services_day."</p>";
			echo "<p>".$row->services_duration."</p>";
            echo "</div>";
		}
	?>
    </div>
</body>
</html>