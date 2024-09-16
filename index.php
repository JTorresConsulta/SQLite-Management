<?php 

ini_set('display_errors', 1);
error_reporting(E_ERROR);


// ACCESS SECURITY /////////////////////////////////////////////////
$username = 'admin';
$password = '1234';

// Verify credentials
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] !== $username || $_SERVER['PHP_AUTH_PW'] !== $password) {
    header('HTTP/1.0 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Protected Area"');
    echo 'Invalid Access';
    exit;
}
//////////////////////////////////////////////////


// Configuration
$db_path = 'users.db';
$recordsPerPage = 100;

function getDBConnection($db_path) {
    try {
        $db = new PDO("sqlite:$db_path");
        return $db;
    } catch (PDOException $e) {
        echo "Error connecting to the database: " . $e->getMessage();
        exit;
    }
}

// Get tables
function getTables($db) {
    $query = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

// Get records from a table with search
function getTableRecords($db, $table, $search = '', $offset = 0, $limit = 100) {
    $search = "%" . $search . "%";
    $columns = array_column(getTableColumns($db, $table), 'name');
    $query = $db->prepare("SELECT * FROM $table WHERE 
        " . implode(" OR ", array_map(fn($col) => "$col LIKE :search", $columns)) . " 
        LIMIT $limit OFFSET $offset");
    $query->bindValue(':search', $search);
    $query->execute();
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

// Get total records matching the search
function getTotalRecords($db, $table, $search = '') {
    $search = "%" . $search . "%";
    $columns = array_column(getTableColumns($db, $table), 'name');
    $query = $db->prepare("SELECT COUNT(*) FROM $table WHERE 
        " . implode(" OR ", array_map(fn($col) => "$col LIKE :search", $columns)));
    $query->bindValue(':search', $search);
    $query->execute();
    return $query->fetchColumn();
}

// Get columns of a table
function getTableColumns($db, $table) {
    $query = $db->query("PRAGMA table_info($table)");
    return $query->fetchAll(PDO::FETCH_ASSOC);
}

// Update record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {

    $db = getDBConnection($db_path);
    $table = $_POST['table'];
    $columns = getTableColumns($db, $table);

    $set = [];
    foreach ($columns as $col) {
        $colName = $col['name'];
        if ($colName !== 'uid' && isset($_POST[$colName])) { // Exclude 'uid' from update
            $set[] = "$colName = :$colName";
        }
    }

    $setString = implode(', ', $set);
    $id = $_POST['id'];
    $sql = "UPDATE $table SET $setString WHERE uid = :id";
    $stmt = $db->prepare($sql);

    foreach ($columns as $col) {
        $colName = $col['name'];
        if ($colName !== 'uid' && isset($_POST[$colName])) { // Exclude 'uid' from update
            $stmt->bindValue(":$colName", $_POST[$colName]);
        }
    }
    $stmt->bindValue(':id', $id);
    $stmt->execute();
    $affectedRows = $stmt->rowCount();

    echo json_encode([
        'ok' => $affectedRows > 0,
        'msg' => $affectedRows > 0 ? "Rows affected: $affectedRows" : "No rows were updated."
    ]);
    exit;
}

// Delete record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $db = getDBConnection($db_path);
    $table = $_POST['table'];
    $id = $_POST['id'];

    // Check if ID is numeric or not
    $idType = is_numeric($id) ? PDO::PARAM_INT : PDO::PARAM_STR;

    // Prepare and execute deletion
    $sql = "DELETE FROM $table WHERE uid = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $id, $idType);
    $stmt->execute();
    $affectedRows = $stmt->rowCount();

    echo json_encode([
        'ok' => $affectedRows > 0,
        'msg' => $affectedRows > 0 ? "Record deleted successfully." : "No record was deleted."
    ]);

    exit;
}

// Interface to display tables and records
$db = getDBConnection($db_path);
$tables = getTables($db);
$table = isset($_GET['table']) ? $_GET['table'] : null;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';
$records = $table ? getTableRecords($db, $table, $search, $offset, $recordsPerPage) : [];
$columns = $table ? getTableColumns($db, $table) : [];
$recordsFound = count($records);
$totalRecords = $table ? getTotalRecords($db, $table, $search) : 0;
$AllRecords = $table ? getTotalRecords($db, $table, '') : 0;
?>

<!DOCTYPE html>
<html lang="en" class="bg-gray-100 dark:bg-zinc-950 dark:text-zinc-300">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQLite Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="../../src/jquery-3.7.0.min.js"></script>
    <script src="../../src/jquery.alterclass.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <style>.button:hover{ filter: brightness(120%);}</style>
</head>
<body class="bg-gray-100 dark:bg-zinc-950 dark:text-zinc-300 min-h-screen flex flex-col" id="app">
 
<div class="container mx-auto p-4">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">SQLite Management</h1>
        <button id="toggleDarkMode" class="button bg-gray-500 dark:bg-zinc-700 text-white dark:text-zinc-300 px-4 py-2 rounded">
            Dark/Light Mode
        </button>
    </div>

    <!-- Search field -->
    <div class="relative mb-6 flex flex-col sm:flex-row justify-between items-center">
        <div id="search_info" class="text-xl text-blue-600 dark:text-blue-500 w-full sm:w-3/5 order-2 sm:order-1 mt-6 sm:mt-0">
            <?php if ($table): ?>
                Showing <?= $recordsFound ?>, found <?= $totalRecords ?> of <?= $AllRecords ?>
            <?php else: ?>
                Select a table to display records
            <?php endif; ?>
        </div>
        <input type="text" id="search" placeholder="Search..." class="bg-white dark:bg-zinc-900 text-black dark:text-white border dark:border-zinc-700 rounded px-4 py-2 w-full mt-2 sm:mt-0 sm:w-3/5 order-1 sm:order-2" value="<?= htmlspecialchars($search) ?>">
    </div> 

    <!-- List of tables -->
    <div class="mb-6">
        <label class="block text-xl font-semibold mb-2">Tables in the database: 
          <span class="font-bold">
            <?php foreach ($tables as $tbl): ?>
                <a href="?table=<?= $tbl['name'] ?>" class="text-blue-500 dark:text-blue-500"><?= $tbl['name'] ?></a>,  
            <?php endforeach; ?>
          </span>
        </label>
    </div>

    <!-- Displaying records of the selected table -->
    <?php if ($table): ?>
        <h2 class="text-xl font-bold mb-6">Records from table: <span class="text-blue-500"><?= htmlspecialchars($table) ?></span></h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">

            <?php foreach ($records as $i => $record): ?>
                <div id="<?= $record['uid'] ?>" class="bg-white dark:bg-zinc-900 p-4 rounded-lg shadow border border-gray-200 dark:border-zinc-800">
                    <?php foreach ($columns as $col): ?>
                        <?php if ($col['name'] !== 'uid'): // Exclude 'uid' field ?>
                            <div class="mb-2">
                                <label class="block text-sm font-semibold"><?= htmlspecialchars($col['name']) ?></label>
                                <div class="contenteditable border dark:border-zinc-700 rounded p-2" contenteditable="true"
                                     data-col="<?= $col['name'] ?>"
                                     data-id="<?= $record['uid'] ?>"><?= htmlspecialchars($record[$col['name']]) ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <button class="update-btn bg-blue-500 text-white px-4 py-2 rounded w-full mt-2 button"
                            data-id="<?= $record['uid'] ?>"
                            data-table="<?= $table ?>">Update
                    </button>
                    <button class="delete-btn bg-red-500 text-white px-4 py-2 rounded w-full mt-2 button"
                            data-id="<?= $record['uid'] ?>"
                            data-table="<?= $table ?>">Delete
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 flex justify-between">
            <a href="?table=<?= $table ?>&offset=<?= max($offset - $recordsPerPage, 0) ?>&search=<?= urlencode($search) ?>"
               class="bg-gray-500 dark:bg-zinc-700 text-white px-4 py-2 rounded button">Previous</a>
            <a href="?table=<?= $table ?>&offset=<?= $offset + $recordsPerPage ?>&search=<?= urlencode($search) ?>"
               class="bg-gray-500 dark:bg-zinc-700 text-white px-4 py-2 rounded button">Next</a>
        </div>
    <?php endif; ?>
</div>

<script>
    // Dark mode
    const toggleDarkMode = document.getElementById('toggleDarkMode');

    // On page load, apply dark mode if saved
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark'); // Add 'dark' to the HTML element
    }

    toggleDarkMode.addEventListener('click', () => {
        // Toggle between dark and light mode on the <html> element
        document.documentElement.classList.toggle('dark');
        
        // Save preference in localStorage
        const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        localStorage.setItem('theme', theme);
    });
    
    // Save the last selected table in localStorage
    $(document).ready(function () {
        const lastTable = localStorage.getItem('lastTable');
        if (lastTable && !window.location.search.includes('table')) {
            window.location.href = '?table=' + lastTable;
        }

        // On table selection, save it to localStorage
        $('a').on('click', function () {
            const table = $(this).attr('href').split('table=')[1];
            localStorage.setItem('lastTable', table);
        });

        // Update records
        $('.update-btn').on('click', function () {
            const id = $(this).data('id');
            const table = $(this).data('table');
            const data = {id: id, table: table, update: true};

            $(this).closest('div').find('.contenteditable').each(function () {
                const col = $(this).data('col');
                data[col] = $(this).text();
            });

            $.post(window.location.href, data, function (res) {
              res = JSON.parse(res)
              if(res.ok){
                $('#'+id).alterClass( 'bg-* dark:bg-*', 'bg-green-700' )
              }else{
                $('#'+id).alterClass( 'bg-* dark:bg-*', 'bg-orange-700' )
              }
              
              setTimeout(() => {
                $('#'+id).alterClass( 'bg-*', 'bg-white dark:bg-zinc-900' )
              }, 2000);
              console.log('Response: ', res);
            });
        });

        // Update records when pressing Enter in the search field
        $('#search').on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault(); // Prevent default Enter behavior (submit form)
                const search = $(this).val();
                const url = new URL(window.location.href);
                url.searchParams.set('search', search);
                url.searchParams.set('offset', 0); // Reset pagination on search
                window.location.href = url.toString();
            }
        });

        // Click handler for the delete button
        $('.delete-btn').on('click', function () {
            if (confirm('Are you sure you want to delete this record?')) {
                const id = $(this).data('id');
                const table = $(this).data('table');
                const data = {id: id, table: table, delete: true};

                $.post(window.location.href, data, function (res) {
                    res = JSON.parse(res);
                    if (res.ok) {
                        $('#' + id).fadeOut(); // Remove record from view
                        setTimeout(() => { location.reload() }, 500); 
                    } else {
                        alert(res.msg);
                    }
                    console.log('Response: ', res);
                });
            }
        });
    });
</script>

</body>
</html>
