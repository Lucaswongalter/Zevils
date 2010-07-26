var PuzzleData = null;

function assert(condition, errMsg) {
  if(!condition) {
    throw errMsg;
  }
}

function setPuzzle(pDict) {
  assert(pDict.grid, "pDict must have grid");
  assert(pDict.clues, "pDict must have clues");
  assert(pDict.clues.across, "pDict must have across clues");
  assert(pDict.clues.down, "pDict must have down clues");

  var gridHeight = pDict.grid.length;
  var gridWidth = pDict.grid[0].length;
  PuzzleData = {grid: new Array(gridWidth),
                clues: {across: [], down: []}};
  
  for(var y = 0; y < gridHeight; y++) {
    assert(pDict.grid[y].length == gridWidth, "Row " + (y+1) + " does not have the same length as row 1!");
  }
  for(var x = 0; x < gridWidth; x++) {
    PuzzleData.grid[x] = new Array(gridHeight);
    for(var y = 0; y < gridHeight; y++) {
      PuzzleData.grid[x][y] = {letter: pDict.grid[y].substr(x, 1),
                               clueAcross: null,
                               clueDown: null,
                               clueStart: false,
                               clueNumber: null};
    }
  }

  var finishClue = function(clue, endX, endY) {
    var answer = "";
    var startX = clue.cell[0];
    var startY = clue.cell[1];
    assert(startX == endX || startY == endY, "Clue start X or Y must match end!");
    
    for(var x = startX; x <= endX; x++) {
      for(var y = startY; y <= endY; y++) {
        answer += PuzzleData.grid[x][y].letter;
      }
    }
    
    clue.answer = answer;
  }
  
  var clueIdx = 0;
  for(var y = 0; y < gridHeight; y++) {
    var clue = null;
    for(var x = 0; x < gridWidth; x++) {
      if(PuzzleData.grid[x][y].letter == " ") {
        if(clue) finishClue(clue, x - 1, y);
        clue = null;
      } else if(!clue) {
        clue = {cell: [x, y],
                text: pDict.clues.across[clueIdx++],
                answer: null};
        PuzzleData.clues.across.push(clue);
        PuzzleData.grid[x][y].clueStart = true;
      }
      PuzzleData.grid[x][y].clueAcross = clue;
    }
    if(clue) finishClue(clue, x - 1, y);
  }

  clueIdx = 0;
  for(var x = 0; x < gridWidth; x++) {
    var clue = null;
    for(var y = 0; y < gridHeight; y++) {
      if(PuzzleData.grid[x][y].letter == " ") {
        if(clue) finishClue(clue, x, y - 1);
        clue = null;
      } else if(!clue) {
        clue = {cell: [x, y],
                text: pDict.clues.down[clueIdx++],
                answer: null};
        PuzzleData.clues.down.push(clue);
        PuzzleData.grid[x][y].clueStart = true;
      }
      PuzzleData.grid[x][y].clueDown = clue;
    }
    if(clue) finishClue(clue, x, y - 1);
  }
  
  assert(PuzzleData.clues.across.length == pDict.clues.across.length, "Across clue count mismatch!");
  assert(PuzzleData.clues.down.length == pDict.clues.down.length, "Down clue count mismatch!");


  var clueNumber = 0;
  for(var y = 0; y < gridHeight; y++) {
    for(var x = 0; x < gridWidth; x++) {
      if(PuzzleData.grid[x][y].clueStart) PuzzleData.grid[x][y].clueNumber = ++clueNumber;
    }
  }  


  displayPuzzle();
}



function displayPuzzle() {
  var gridHTML = "";
  for(var y = 0; y < PuzzleData.grid[0].length; y++) {
    gridHTML += "<tr>";
    for(var x = 0; x < PuzzleData.grid.length; x++) {
      var letter = PuzzleData.grid[x][y].letter;
      if(letter == " ") {
        gridHTML += '<td class="cell blackCell">&nbsp;</td>';
      } else {
        gridHTML += '<td class="cell">';
        var clueNumber = PuzzleData.grid[x][y].clueNumber;
        if(clueNumber) gridHTML += '<div class="clueNumber">' + clueNumber + '</div>';
        gridHTML += '<div class="gridLetter">' + letter + '</div></td>';
      }
    }
    gridHTML += "</tr>";
  }
  $("#grid").html(gridHTML);
  
  var clueListHTML = "<p>Across</p><ol>";
  for(var clueIdx in PuzzleData.clues.across) {
    var clue = PuzzleData.clues.across[clueIdx];
    clueListHTML += '<li value="' + clue.clueNumber + '">' + clue.text + '</li>';
  }
  clueListHTML += "</ol>";
  $("#cluesAcross").html(clueListHTML);

  clueListHTML = "<p>Down</p><ol>";
  for(var clueIdx in PuzzleData.clues.down) {
    var clue = PuzzleData.clues.down[clueIdx];
    clueListHTML += '<li value="' + clue.clueNumber + '">' + clue.text + '</li>';
  }
  clueListHTML += "</ol>";
  $("#cluesDown").html(clueListHTML);
}
