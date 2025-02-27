<?php  
session_start();
$conn = null;
include "../db_conn.php";

if (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['role'])) {

	function test_input($data) {
	  $data = trim($data);
	  $data = stripslashes($data);
	  $data = htmlspecialchars($data);
	  return $data;
	}

	$username = test_input($_POST['username']);
	$password = test_input($_POST['password']);
	$role = test_input($_POST['role']);

	if (empty($username)) {
		header("Location: ../index.php?error=User Name is Required");
	}else if (empty($password)) {
		header("Location: ../index.php?error=Password is Required");
	}else {

        $root_folder = "/tmp";
        $table_name = "users";

		// Hashing the password
		$password = md5($password);
        
        $sql = "SELECT * FROM ".$table_name." WHERE username='$username' AND password='$password'";
        $result = mysqli_query($conn, $sql);

        if( !$result )
            header("Location: ../index.php?error=Incorect User name or password");

        $result_check = mysqli_num_rows($result);



        if ( $result_check === 1) {
        	// the user name must be unique
        	$row = mysqli_fetch_assoc($result);
        	if ($row['password'] === $password && $row['role'] == $role) {
        		$_SESSION['name'] = $row['name'];
        		$_SESSION['id'] = $row['id'];
        		$_SESSION['role'] = $row['role'];
        		$_SESSION['username'] = $row['username'];
                $_SESSION['folder'] = $row['folder'];

                $user_directory = $_SESSION['folder'];
                if( $user_directory === "null")
                {
                    $c = uniqid (rand (),true);
                    $user_directory = $root_folder."/".$c;
                    $sql = "UPDATE ".$table_name." SET folder = '".$user_directory."' WHERE username='".$_SESSION['username']."'";
                    $result = mysqli_query($conn, $sql);
                    $_SESSION['folder'] = $user_directory;
                }
                if (!file_exists($user_directory) ) {
                    mkdir($user_directory, 0777, true);
                }
                $file = $user_directory.'/.panconfkeystore';
                if(!is_file($file)){
                    $contents = '';           // Some simple example content.
                    file_put_contents($file, $contents);     // Save our content to the file.
                }

        		header("Location: ../home.php");

        	}else {
        		header("Location: ../index.php?error=Incorect User name or password");
        	}
        }else {
        	header("Location: ../index.php?error=Incorect User name or password");
        }

	}
	
}else {
	header("Location: ../index.php");
}