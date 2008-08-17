#!/usr/bin/perl

use strict;
use warnings;

sub escape {
  my $str = shift;
  $str =~ s/'/''/g;
  return $str;
}

my $group_id = 19678;
while(<>) {
  if(/^-- (.*), ([01]):/) {
    $group_id++;
    my($street_name, $dessert) = ($1, $2);
    printf("\nINSERT INTO groups(group_id, street_name, rehearsal_dessert_invite) VALUES (%d, '%s', %d);\n",
           $group_id, escape($street_name), $dessert);
  } elsif(/^ *[+]{2} (.*)/) {
    my $name = $1;
    printf("INSERT INTO people(group_id, name) values (%d, '%s');\n",
           $group_id,
           escape($name));
  }
}
