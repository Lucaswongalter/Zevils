#!/usr/bin/perl

use strict;
use warnings;
use Text::CSV::Simple;

sub find_addr {
  my @candidates = @_;

  foreach my $candidate (@candidates) {
    next unless $candidate =~ / (STREET|ST|PLACE|PL|PLACE|AVE|AVENUE|DRIVE|DR|COURT|CT|EXPRESSWAY|EXPY|PARKWAY|PKWY|TURNPIKE|TPKE|BLVD|BOULEVARD|BOLEVARD|CIR|CIRCLE|LN|LANE|RD|ROAD|TER|TERR|TERRACE|WAY)\.?$/i
      or
        $candidate =~ /BOX /i;
    return $candidate;
  }
  foreach my $candidate (@candidates) {
    return $candidate if $candidate;
  }
}

sub split_names {
  my $namestr = shift;
  my @in_names = split(qr![,/]!, $namestr);
  my @out_names;
  foreach my $name (@in_names) {
    $name =~ s/^ +//;
    $name =~ s/ +$//;
    $name =~ s/^and +//i;
    if($name =~ /^ *(.+?) +and +(.+?) *$/i) {
      push @out_names, $1, $2;
    } else {
      push @out_names, $name;
    }
  }
  return @out_names;
}

our @FIELDS = (
               [""],
               ["Last", "lastnames"],
               ["First", "firstnames"],
               ["Extras", "extras"],
               ["Phone"],
               ["Email"],
               ["Total in party", "guestcount"],
               ["RSVP"],
               ["Save Date"],
               ["Invitation Addressed"],
               ["Rehearsal Dinner", "dessert_invite"],
               ["One"],
               ["", "addr0"],
               ["Address 1", "addr1"],
               ["Address 2", "addr2"]
);

die "Usage: $0 filename\n" unless @ARGV == 1;

my $csv = Text::CSV::Simple->new;
$csv->field_map(map { $_->[1] || "null" } @FIELDS);
my @groups = $csv->read_file(shift);

shift @groups if $groups[0]->{addr1} eq "Address 1";


foreach my $group (@groups) {
  my @guests;

  my @group_warnings;
  my $addr = find_addr($group->{addr0},
                       $group->{addr1},
                       $group->{addr2},
                       $group->{extras});

  my @last_names = split_names($group->{lastnames});
  my @first_names = split_names($group->{firstnames});
  if($group->{extras} !~ /Century Village|Student Mailbox/) {
    push @first_names, split_names($group->{extras});
  }

  if(!@last_names) {
    push @group_warnings, "   ** Need last name!";
    @last_names = ("???");
  }

  my $has_pseudo_guest;
  while(@guests < $group->{guestcount} and @first_names) {
    my $first = shift(@first_names);
    my $last = $last_names[0];
    $first =~ s/^(mr|mrs|ms|miss|dr|rev)\.? //i;
    $has_pseudo_guest = 1 if $first =~ /^(family|guest)$/i;
    shift @last_names if @last_names > 1;
    push @guests, "$first $last";
  }

  if(!$addr) {
    push @group_warnings, "   ** Need address!";
  }

  if($has_pseudo_guest) {
    push @group_warnings, "   ** Need real guest names!";
  } elsif(@guests != $group->{guestcount}) {
    push @group_warnings, "   ** Guest count doesn't match!";
  }

  my $street = $addr;
  $street =~ s/([a-z])([A-Z])/$1 $2/g;
  $street = uc($street);
  $street =~ s/ *\(.*?\) *//;
  $street =~ s/,.*//;
  $street =~ s/ +(APARTMENT|APT\.?) .*//;
  $street =~ s/(?!BOX) +#\d+ *//;
  $street =~ s/ +[A-Z][0-9]*$//;
  $street =~ s/\bSOUTHWEST\b/SW/;
  $street =~ s/\bSOUTHEAST\b/SE/;
  $street =~ s/\bNORTHWEST\b/NW/;
  $street =~ s/\bNORTH\b/N/;
  $street =~ s/\bSOUTH\b/S/;
  $street =~ s/\bEAST\b/E/;
  $street =~ s/^(\d+) (.*) (N|S|E|W|NW|SW|SE)$/$1 $3 $2/;
  $street =~ s/ (STREET|ST|PLACE|PL|PLACE|AVE|AVENUE|DRIVE|DR|COURT|CT|EXPRESSWAY|EXPY|PARKWAY|PKWY|TURNPIKE|TPKE|BLVD|BOULEVARD|BOLEVARD|CIR|CIRCLE|LN|LANE|RD|ROAD|TER|TERR|TERRACE|WAY)\.?$//;
  $street =~ s/\bWEST\b/W/;

  my $rehearsal = 0;
  $rehearsal = 1 if $group->{dessert_invite} =~ /^Inv/i;
  printf("-- %s, %d:\n   .. %s, %s, %s (=%d) // %s / %s / %s // %s\n",
         $street, $rehearsal,
         $group->{lastnames}, $group->{firstnames}, $group->{extras},
         $group->{guestcount},
         $group->{addr0}, $group->{addr1}, $group->{addr2},
         $group->{dessert_invite});
  if(@group_warnings) {
    print join("\n", @group_warnings), "\n";
  }

  foreach my $guest (@guests) {
    printf("   ++ $guest\n");
  }
}
