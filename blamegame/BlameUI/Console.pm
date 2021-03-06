package BlameUI::Console;

use strict;
use warnings;
use Term::Cap;
use Term::ReadKey;
use Term::ANSIColor qw(:constants);
use POSIX qw(floor ceil);
use List::Util qw(max min);
use Carp qw(confess);
use File::Basename;

use constant KEY_LEFT => 68;
use constant KEY_RIGHT => 67;
use constant KEY_UP => 65;
use constant KEY_DOWN => 66;

our $IN_READMODE = 0;
END { ReadMode(0) if $IN_READMODE; }

sub new {
  my($class, $urlBase, $diffData, $oldPath, $oldRev, $newPath, $newRev, $copyURL, $copyRev) = @_;
  $class = ref($class) || $class || __PACKAGE__;
  my $self = {
              urlBase => $urlBase,
              terminal => Term::Cap->Tgetent(),
              oldPath => $oldPath,
              oldRev => $oldRev,
              newPath => $newPath,
              newRev => $newRev,
              copyURL => $copyURL,
              copyRev => $copyRev,
              diffData => $diffData,
             };
  bless $self, $class;
  $self;
}

sub setReadMode {
  my($self, $mode) = @_;
  # Don't allow switching directly from one to the other.
  # We want ReadMode(0) to always reset to the mode before
  # the program started.
  ReadMode(0) if $IN_READMODE and $mode;

  ReadMode($mode);
  $IN_READMODE = $mode;
}

sub findLine {
  my($self, $termLines, $target, $lineTransform) = @_;

  my($lbound, $ubound) = (0, scalar(@$termLines));
  while($lbound <= $ubound) {
    my $thisLineNo = $lbound + floor(($ubound - $lbound) / 2);
    return unless defined($termLines->[$thisLineNo]);
    my $thisValue = $lineTransform->($termLines->[$thisLineNo]);
    if($thisValue < $target) {
      $lbound = $thisLineNo + 1;
    } elsif($thisValue > $target) {
      $ubound = $thisLineNo - 1;
    } else {
      return $thisLineNo;
    }
  }

  return $ubound;
}

sub computeBoundaries {
  my($self) = @_;

  $self->{maxFileLine} = @{$self->{termLines}} ?
    $self->{termLines}->[-1]->[0] + 1 :
    0;
  $self->{lineDigits} = $self->{maxFileLine} ?
    floor(log($self->{maxFileLine}) / log(10)) + 1 :
    1;
  $self->{displayLines} = $self->{termHeight} - 1;
  $self->{lastTermLine} = scalar(@{$self->{termLines}});
  $self->{maxStartTermLine} = max($self->{lastTermLine} - $self->{displayLines},
                                  0);
}

sub setMode {
  my($self, $mode) = @_;
  # If we're at the first revision of the file, there's no diff
  # data, so force display of the log message.
  $mode = "log" unless $self->{diffData}->{$mode} and @{$self->{diffData}->{$mode}};

  my $oldMode = $self->{mode};
  my $oldLines = $self->{termLines};
  $self->{mode} = $mode;

  if($mode eq "log") {
    $self->{oldStartTermLine} = $self->{startTermLine};
    $self->{startTermLine} = 0;
  } elsif($oldMode eq "log") {
    $self->{startTermLine} = $self->{oldStartTermLine};
  }
  $self->{termLines} = $self->{diffData}->{$mode};

  if($self->{startTermLine} and $oldMode ne "log") {
    my $lines = $self->{termLines};
    my $startLine = $self->{startTermLine};
    my($old_lineno, $new_lineno) = ($oldLines->[$startLine]->oldFileLine,
                                    $oldLines->[$startLine]->newFileLine);

    my $get_line_number;
    # Pick where to scroll to by trying to keep either the old or
    # new line number constant.  Use whichever of the two there are
    # more of to maximize precision.
    if($old_lineno and $new_lineno and $old_lineno > $new_lineno) {
      $get_line_number = sub { shift->oldFileLine; };
    } else {
      $get_line_number = sub { shift->newFileLine; };
    }

    if($lines->[$startLine]) {
      my $target = $get_line_number->($oldLines->[$startLine]);
      $self->{startTermLine} = $self->findLine($lines,
                                               $target,
                                               $get_line_number);
    } else {
      $self->{startTermLine} = 0;
    }
  }

  $self->computeBoundaries() if $self->{termHeight};
}

sub updateGeometry {
  my($self) = @_;
  my($termWidth, $termHeight) = GetTerminalSize();
  $self->{termWidth} = $termWidth;
  $self->{termHeight} = $termHeight;
  $self->computeBoundaries() if $self->{mode};
}

sub scroll {
  my($self, $distance) = @_;
  $self->{startTermLine} += $distance;
  if($distance < 0) {
    $self->{startTermLine} = max($self->{startTermLine},
                                 0);
  } else {
    $self->{startTermLine} = min($self->{startTermLine},
                                 $self->{maxStartTermLine});
  }
}

sub clear {
  my($self) = @_;
  $self->{terminal}->Tputs("cl", 1, *STDOUT);
}

sub promptLine {
  my($self, $prompt, $noEcho) = @_;
  $self->setReadMode($noEcho ? 2 : 1);
  $self->{terminal}->Tputs("cr", 1, *STDOUT);
  $self->{terminal}->Tputs("ce", 1, *STDOUT);
  print $prompt;

  chomp(my $ret = ReadLine(0));
  $self->setReadMode(4);
  $ret;
}

sub showError {
  my($self, $errText) = @_;
  my $oldReadMode = $IN_READMODE;
  $self->clear();
  print BOLD, "Error: ", RESET, $errText, "\n";
  $self->promptLine("Press return to continue...\n", 1);
  $self->setReadMode($oldReadMode);
}

sub showDiff {
  my($self, $scrollToFileLine) = @_;

  local $SIG{WINCH} = sub {
    $self->updateGeometry();
  };
  $self->setMode("mixed");
  $self->updateGeometry();

  $self->{startTermLine} = 0;
  if($scrollToFileLine and $self->{mode} ne "log") {
    $self->{startTermLine} = $self->findLine(
                                             $self->{termLines},
                                             $scrollToFileLine,
                                             sub { shift->oldFileLine(); }
                                            );
  }

  $self->setReadMode(4);
  my($lastSearch, $searchMode);
  while(1) {
    $self->clear();
    my $endTermLine = $self->{startTermLine} + $self->{displayLines};

    my $searchFound = 0;
    while(!$searchFound) {
      $searchFound = 1 unless $searchMode;

      my $wrappedLines = 0;
      for(my $termLine = 0; ($termLine + $wrappedLines) < $self->{displayLines}; $termLine++) {
        my $fileLineNo = $termLine + $self->{startTermLine};
        my $line = $self->{termLines}->[$fileLineNo];
        last unless $line;
        my $lineText = $line->lineText;
        if($lineText =~ /^\@\@/) {
          print BOLD, "...\n", RESET;
        } else {
          my $lineLength = max(length($lineText) - 1, 0);
          $lineLength += $self->{lineDigits} + 1 unless $self->{mode} eq "log";
          $wrappedLines += max(floor($lineLength/$self->{termWidth}), 0);

          printf "%s%$self->{lineDigits}d ", RED, $line->oldFileLine
            unless $self->{mode} eq "log";
          if($lastSearch and
             $lineText =~ s/($lastSearch)/REVERSE . $1 . RESET/ge
          ) {
            $searchFound = 1;
          }
          if($lineText =~ /^-/) {
            print "$lineText\n", RESET;
          } elsif($lineText =~ /^\+/) {
            print GREEN, "$lineText\n", RESET;
          } else {
            print RESET, "$lineText\n";
          }
        }
      }

      if($searchMode and !$searchFound) {
        if($searchMode eq "?") {
          last if $self->{startTermLine} == 0;
          $self->scroll(-$self->{displayLines});
        } else {
          last if $self->{startTermLine} == $self->{maxStartTermLine};
          $self->scroll($self->{displayLines});
        }
      }
    }

    my $branchFlag = "-";
    $branchFlag = "c" if $self->{copyURL};
    my $promptLineTemplate = "== $branchFlag [$self->{mode}] $self->{startTermLine}-$endTermLine/$self->{lastTermLine} %s->%s ==";

    my $oldDisplayPath = $self->{oldPath} . '@' . $self->{oldRev};
    my $newDisplayPath = $self->{newPath} . '@' . $self->{newRev};
    $oldDisplayPath =~ s!^\Q$self->{urlBase}\E!!;
    $newDisplayPath =~ s!^\Q$self->{urlBase}\E!!;
    my $promptLineLength =
      # -4 for %s %s
      length($promptLineTemplate) - 4 +
      length($oldDisplayPath) +
      length($newDisplayPath);
    if($promptLineLength > $self->{termWidth}) {
      # +6 for ... ...
      my $trimChars = $promptLineLength - $self->{termWidth} + 6;
      my $trimOld = floor($trimChars / 2);
      my $trimNew = ceil($trimChars / 2);
      substr($oldDisplayPath, 0, $trimOld) = "...";
      substr($newDisplayPath, 0, $trimNew) = "...";
    }

    print 
      REVERSE,
      sprintf($promptLineTemplate, $oldDisplayPath, $newDisplayPath),
      RESET;

    my $key = ReadKey(0);
    undef $searchMode;
    if($key eq " ") {
      $self->scroll($self->{displayLines});
    } elsif(ord($key) == KEY_DOWN) {
      $self->scroll(1);
    } elsif($key eq "b") {
      $self->scroll(-$self->{displayLines});
    } elsif(ord($key) == KEY_UP) {
      $self->scroll(-1);
    } elsif($key eq "q") {
      print "\n";
      $self->setReadMode(0);
      exit;
    } elsif($key eq "o") {
      $self->setMode("old");
    } elsif($key eq "n") {
      $self->setMode("new");
    } elsif($key eq "m") {
      $self->setMode("mixed");
    } elsif($key eq "/" or $key eq "?") {
      $searchMode = $key;

      my $search = $self->promptLine($key);
      if(!$search and $lastSearch) {
        $search = $lastSearch;

        if($key eq "/") {
          $self->scroll($self->{displayLines});
        } else {
          $self->scroll(-$self->{displayLines});
        }
      }
      $lastSearch = $search;

      $self->setReadMode(4);
    } elsif($key eq "l") {
      $self->setMode("log");
    } elsif($key eq "r") {
      my $newTargetLine = $self->promptLine("New line number: ");
      if($newTargetLine) {
        $self->setReadMode(0);
        return $newTargetLine;
      }
    } elsif($key eq "p") {
      my $default = $self->{copyURL};
      my $dfdata = $default ? " ($default)" : "";
      my $newTargetPath = $self->promptLine("New target path$dfdata: ");
      $newTargetPath ||= $default;

      if($newTargetPath) {
        if($newTargetPath !~ m!://!) {
          $newTargetPath =~ s!^/!!;
          my $base = $self->{urlBase};
          $base =~ s!/$!!;
          $newTargetPath = "$base/$newTargetPath";
        }

        $default = $self->{copyRev} || $self->{oldRev};
        $dfdata = $default ? " ($default)" : "";
        my $newTargetRev = $self->promptLine("New target revision$dfdata: ");
        $newTargetRev ||= $default;
        if($newTargetPath and $newTargetRev) {
          $self->setReadMode(0);
          return $scrollToFileLine,
            $newTargetPath,
            $newTargetRev,
            $self->{oldPath},
            $self->{oldRev};
        }
      }
    } elsif($key eq "h") {
      $self->clear();
      print BOLD, "Summary of commands:\n", RESET;
      print <<EOF;
   SPACE, b: Page down, up
   Down arrow, Up arrow: Line down, up
   /, ?: Search forwards, backwards
   l: Show commit log message
   r: Recurse to and older revision
   p: Change target path
   o, n, m: Only show lines in old version, new version, both
   q: Quit

EOF
      $self->promptLine("Press return to continue...\n", 1);
    }
  }

  $self->setReadMode(0);
}

1;
