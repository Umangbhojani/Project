<?php
include 'Mysql.php';

// Fetch approved members
$sql = "SELECT id, team_name, username FROM approved_members";
$result = $conn->query($sql);

$members = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Typing Race Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #1a1a1a;
            color: #ffffff;
            margin: 0;
            padding: 20px;
            font-size: 18px;
            font-weight: bold;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1, h2 {
            text-align: center;
            font-size: 36px;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            font-size: 22px;
        }
        .stat {
            text-align: center;
        }
        #text-display {
            background-color: #2a2a2a;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 24px;
            min-height: 100px;
            line-height: 1.5;
        }
        #input-area {
            width: 100%;
            height: 120px;
            background-color: #333;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 20px;
        }
        .buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        button {
            padding: 15px 30px;
            font-size: 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .difficulty {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .difficulty button {
            background-color: #008CBA;
        }
        .difficulty button:hover {
            background-color: #007B9A;
        }
        #team-management, #leaderboard {
            background-color: #2a2a2a;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 20px;
        }
        #team-members {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .team-member {
            background-color: #333;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            font-size: 20px;
            text-align: left;
        }
        th {
            background-color: #333;
        }
        .correct {
            color: green;
        }
        .incorrect {
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Typing Race Dashboard</h1>

        <div class="difficulty">
            <button id="easy-btn">Easy</button>
            <button id="normal-btn">Normal</button>
            <button id="hard-btn">Hard</button>
            <button id="very-hard-btn">Very Hard</button>
        </div>

        <div class="stats">
            <div class="stat">
                <h3>WPM</h3>
                <p id="wpm">0</p>
            </div>
            <div class="stat">
                <h3>Accuracy</h3>
                <p id="accuracy">0%</p>
            </div>
            <div class="stat">
                <h3>Time</h3>
                <p id="time">0:00</p>
            </div>
        </div>
        <div id="text-display"></div>
        <textarea id="input-area" placeholder="Start typing here..." disabled></textarea>
        <div class="buttons">
            <button id="start-btn">Start</button>
            <button id="stop-btn" disabled>Stop</button>
        </div>

        <div id="team-management">
            <h2>Team Management</h2>
            <input type="text" id="team-member-input" placeholder="Enter team member name">
            <button id="add-member-btn">Add Member</button>
            <div id="team-members"></div>
        </div>

        <div id="leaderboard">
            <h2>Leaderboard</h2>
            <table id="leaderboard-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Name</th>
                        <th>WPM</th>
                        <th>Accuracy</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <h2>Select Members for the Race</h2>
        <div id="member-selection">
            <?php foreach ($members as $member): ?>
                <div>
                    <input type="checkbox" class="member-checkbox" value="<?php echo $member['username']; ?>">
                    <?php echo $member['username']; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button id="start-race-btn">Start Race</button>
        <div id="race-result" style="display: none;">
            <h2>Race Results</h2>
            <table id="race-result-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>WPM</th>
                        <th>Accuracy</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script>
        // Game variables
        let timeLeft = 60;
        let timer;
        let isPlaying = false;
        let text = "";
        let typedText = "";
        let currentIndex = 0;
        let startTime;
        let wpm = 0;
        let accuracy = 0;
        let difficulty = "normal";
        let teamMembers = [];
        let leaderboard = [];
        let selectedMembers = [];

        // DOM elements
        const textDisplay = document.getElementById("text-display");
        const inputArea = document.getElementById("input-area");
        const startBtn = document.getElementById("start-btn");
        const stopBtn = document.getElementById("stop-btn");
        const wpmDisplay = document.getElementById("wpm");
        const accuracyDisplay = document.getElementById("accuracy");
        const timeDisplay = document.getElementById("time");
        const difficultyBtns = document.querySelectorAll(".difficulty button");
        const addMemberBtn = document.getElementById("add-member-btn");
        const teamMemberInput = document.getElementById("team-member-input");
        const teamMembersDisplay = document.getElementById("team-members");
        const leaderboardTable = document.getElementById("leaderboard-table").getElementsByTagName('tbody')[0];
        const raceResultTable = document.getElementById("race-result-table").getElementsByTagName('tbody')[0];
        const startRaceBtn = document.getElementById("start-race-btn");
        const memberCheckboxes = document.querySelectorAll(".member-checkbox");

        // Sample texts for different difficulties
        const texts = {
            easy: "The quick brown fox jumps over the lazy dog.",
            normal: "The sun was setting behind the mountains, casting a warm glow across the valley.",
            hard: "In quantum mechanics, the uncertainty principle states that the more precisely the position of a particle is determined, the less precisely its momentum can be predicted.",
            veryHard: "The intricate interplay between genetic predisposition and environmental factors in the development of complex behavioral traits remains a subject of intense scientific scrutiny."
        };

        // Event listeners
        startBtn.addEventListener("click", startGame);
        stopBtn.addEventListener("click", stopGame);
        difficultyBtns.forEach(btn => btn.addEventListener("click", changeDifficulty));
        addMemberBtn.addEventListener("click", addTeamMember);
    startRaceBtn.addEventListener("click", startRace);

    // Functions
    function startGame() {
        if (isPlaying) return;
        resetGame();
        text = texts[difficulty];
        textDisplay.innerText = text;
        inputArea.disabled = false;
        inputArea.focus();
        startTime = Date.now();
        timer = setInterval(updateTimer, 1000);
        isPlaying = true;
    }

    function resetGame() {
        typedText = "";
        currentIndex = 0;
        timeLeft = 60;
        wpm = 0;
        accuracy = 0;
        inputArea.value = "";
        wpmDisplay.innerText = wpm;
        accuracyDisplay.innerText = accuracy + "%";
        timeDisplay.innerText = "0:00";
        clearInterval(timer);
        isPlaying = false;
    }

    function stopGame() {
        clearInterval(timer);
        isPlaying = false;
        calculateResults();
    }

    function updateTimer() {
        timeLeft--;
        if (timeLeft <= 0) {
            stopGame();
        }
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timeDisplay.innerText = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
    }

    function calculateResults() {
        const totalTime = (Date.now() - startTime) / 1000;
        const totalTypedWords = typedText.split(" ").length;
        wpm = Math.round((totalTypedWords / totalTime) * 60);
        accuracy = Math.round((typedText.length / text.length) * 100);
        wpmDisplay.innerText = wpm;
        accuracyDisplay.innerText = accuracy + "%";
        inputArea.disabled = true;
    }

    function changeDifficulty(event) {
        difficulty = event.target.innerText.toLowerCase();
        resetGame();
    }

    function addTeamMember() {
        const memberName = teamMemberInput.value.trim();
        if (memberName) {
            const memberDiv = document.createElement("div");
            memberDiv.className = "team-member";
            memberDiv.innerText = memberName;
            teamMembersDisplay.appendChild(memberDiv);
            teamMembers.push(memberName);
            teamMemberInput.value = "";
        }
    }

    function startRace() {
        selectedMembers = Array.from(memberCheckboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);

        if (selectedMembers.length === 0) {
            alert("Please select at least one member for the race.");
            return;
        }

        // Simulate race results
        raceResultTable.innerHTML = ""; // Clear previous results
        selectedMembers.forEach(member => {
            const row = raceResultTable.insertRow();
            const nameCell = row.insertCell(0);
            const wpmCell = row.insertCell(1);
            const accuracyCell = row.insertCell(2);
            nameCell.innerText = member;
            wpmCell.innerText = Math.floor(Math.random() * (100 - 40 + 1)) + 40; // Random WPM between 40 and 100
            accuracyCell.innerText = Math.floor(Math.random() * (100 - 80 + 1)) + 80 + "%"; // Random accuracy between 80% and 100%
        });

        document.getElementById("race-result").style.display = "block";
    }
</script>
