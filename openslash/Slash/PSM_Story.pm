package Slash::PSM::SlashSite::Story;
use strict;
use Carp qw(cluck croak carp confess);
no strict 'subs';
use vars qw(@ISA %rfields %wfields $AUTOLOAD);
@ISA = ('Slash::PSM');

sub AUTOLOAD {
	my($self, %rfields, %wfields) = shift;

	return unless ref($self) eq "Slash::PSM::Story" or $self eq "Slash::PSM::Story"; #Don't allow this method to be inherited.
	for my $attr (qw()) { $rfields{$attr}++; }
	for my $attr (qw()) { $wfields{$attr}++; }

	$_[0]->_autoload(\%rfields, \%wfields);
}

1;

