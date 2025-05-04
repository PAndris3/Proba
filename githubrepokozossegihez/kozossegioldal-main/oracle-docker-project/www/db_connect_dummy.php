<?php
// db_connect_dummy.php
// Ez csak tesztelési célokat szolgál, valódi adatbázis-kapcsolat nélkül

class DummyConnection {
    public function dummyQuery($query) {
        // Imitálja a lekérdezés eredményét
        if (strpos($query, "FELHASZNALO") !== false) {
            return [
                ["USERID" => 1, "NEV" => "Kiss Péter", "EMAIL" => "kiss.peter@gmail.com", "ALLAPOT" => "aktív"],
                ["USERID" => 2, "NEV" => "Nagy Anna", "EMAIL" => "nagy.anna@gmail.com", "ALLAPOT" => "inaktív"],
                ["USERID" => 3, "NEV" => "Szabó László", "EMAIL" => "szabo.laszlo@gmail.com", "ALLAPOT" => "törölt"]
            ];
        }
        return [];
    }
}

$conn = new DummyConnection();
echo "<!-- Dummy adatbázis-kapcsolat létrehozva -->";
?>