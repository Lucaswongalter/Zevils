=pod

Net::OSCAR::XML -- XML functions for Net::OSCAR

We're doing the fancy-schmancy Protocol.xml stuff here, so I'll explain it here.

Protocol.xml contains a number of "OSCAR protocol elements".  One E<lt>defineE<gt> block
is one OSCAR protocol elemennt.

When the module is first loaded, Protocol.xml is parsed and two hashes are created,
one whose keys are the names the the elements and whose values are the contents
of the XML::Parser tree which represents the contents of those elements; the other
hash has a family/subtype tuple as a key and element names as a value.

To do something with an element, given its name, Net::OSCAR calls C<protoparse("element name")>.
This returns a C<Net::OSCAR::XML::Template> object, which has C<pack> and C<unpack> methods.
C<pack> takes a hash and returns a string of binary characters, and C<unpack> goes the
other way around.  The objects are cached, so C<protoparse> only has to do actual work once
for every protocol element.

=cut

package Net::OSCAR::XML;

$VERSION = '1.11';
$REVISION = '$Revision$';

use strict;
use vars qw(@ISA @EXPORT $VERSION);
use XML::Parser;
use Carp;
use Data::Dumper;

use Net::OSCAR::XML::Template;
use Net::OSCAR::Common qw(:loglevels);
use Net::OSCAR::Utility qw(hexdump);
use Net::OSCAR::TLV;
our(%xmlmap, %xml_revmap, $PROTOPARSE_DEBUG);

require Exporter;
@ISA = qw(Exporter);
@EXPORT = qw(
	protoparse protobit_to_snacfam snacfam_to_protobit
);

$PROTOPARSE_DEBUG = 0;

sub _protopack($$;@);
sub _xmlnode_to_template($$);

sub load_xml(;$) {
	my $xmlparser = new XML::Parser(Style => "Tree");

	my $xmlfile = "";
	if($_[0]) {
		$xmlfile = shift;
	} else {
		foreach (@INC) {
			next unless -f "$_/Net/OSCAR/XML/Protocol.xml";
			$xmlfile = "$_/Net/OSCAR/XML/Protocol.xml";
			last;
		}
		croak "Couldn't find Net/OSCAR/XML/Protocol.xml in search path: " . join(" ", @INC) unless $xmlfile;
	}

	open(XMLFILE, $xmlfile) or croak "Couldn't open $xmlfile: $!";
	my $xml = join("", <XMLFILE>);
	close XMLFILE;
	my $xmlparse = $xmlparser->parse($xml) or croak "Couldn't parse XML from $xmlfile: $@";
	%xmlmap = ();
	%xml_revmap = ();
	# We set the autovivification so that keys of xml_revmap are Net::OSCAR::TLV hashrefs.
	if(!tied(%xml_revmap)) {
		tie %xml_revmap, "Net::OSCAR::TLV", 'tie %$value, ref($self)';
	}

	my @tags = @{$xmlparse->[1]}; # Get contents of <oscar>
	shift @tags;
	while(@tags) {
		my($name, $value);
		(undef, undef, $name, $value) = splice(@tags, 0, 4);
		next unless $name and $name eq "define";
	
		$xmlmap{$value->[0]->{name}} = {xml => $value};
		if($value->[0]->{family}) {
			$xmlmap{$value->[0]->{name}}->{family} = $value->[0]->{family};
			$xmlmap{$value->[0]->{name}}->{subtype} = $value->[0]->{subtype};
			$xmlmap{$value->[0]->{name}}->{channel} = $value->[0]->{channel};
			$xml_revmap{$value->[0]->{family}}->{$value->[0]->{subtype}} = $value->[0]->{name};
		}
	}

	return 1;
}

sub _num_to_packlen($$) {
	my($type, $order) = @_;
	$order ||= "network";

	if($type eq "byte") {
		return ("C", 1);
	} elsif($type eq "word") {
		if($order eq "vax") {
			return ("v", 2);
		} else {
			return ("n", 2);
		}
	} elsif($type eq "dword") {
		if($order eq "vax") {
			return ("V", 4);
		} else {
			return ("N", 4);
		}
	}

	croak "Invalid num type: $type";
}

# Specification for OSCAR protocol template:
#	-Listref whose elements are hashrefs.
#	-Hashrefs have following keys:
#		type: "ref", "num", "data", or "tlvchain"
#		If type = "num":
#			packlet: Pack template letter (C, n, N, v, V)
#			len: Length of datum, in bytes
#		If type = "data":
#			Arbitrary data
#			If prefix isn't present, all available data will be gobbled.
#			len (optional): Size of datum, in bytes
#		If type = "ref":
#			name: Name of protocol bit to punt to
#		If type = "tlvchain":
#			subtyped: If true, this is a 'subtyped' TLV, as per Protocol.dtd.
#			prefix: If present, "count" or "length", and "packlet" and "len" will also be present.
#			items: Listref containing TLVs, hashrefs in format identical to these, with extra key 'num' (and 'subtype', for subtyped TLVs.)
#		value: If present, default value of this datum.
#		name: If present, name in parameter list that this datum gets.
#		count: If present, number of repetitions of this datum.  count==-1 represents
#			infinite.  If a count is present when unpacking, the data will be encapsulated in a listref.  If the user
#			wants to pass in multiple data when packing, they should do so via a listref.  Listref-encapsulated data with
#			too many elements for the 'count' will trigger an exception when packing.
#		prefix: If present, either "count" or "length", and indicates that datum has a prefix indicating its length.
#			prefix_packet, prefix_len: As per "num".
#
sub _xmlnode_to_template($$) {
	my($tag, $value) = @_;

	confess "Invalid value in xmlnode_to_template!" unless ref($value);
	my $attrs = shift @$value;

	my $datum = {};
	$datum->{name} = $attrs->{name} if $attrs->{name};
	$datum->{value} = $value->[1] if @$value and $value->[1] =~ /\S/;
	$datum->{items} = [];

	$datum->{count} = $attrs->{count} if $attrs->{count};
	if($attrs->{count_prefix} || $attrs->{length_prefix}) {
		my($packlet, $len) = _num_to_packlen($attrs->{count_prefix} || $attrs->{length_prefix}, $attrs->{prefix_order});
		$datum->{prefix_packlet} = $packlet;
		$datum->{prefix_len} = $len;
		$datum->{prefix} = $attrs->{count_prefix} ? "count" : "length";
	}


	if($tag eq "ref") {
		$datum->{type} = "ref";
	} elsif($tag eq "byte" or $tag eq "word" or $tag eq "dword") {
		$datum->{type} = "num";

		my($packlet, $len) = _num_to_packlen($tag, $attrs->{order});
		$datum->{packlet} = $packlet;
		$datum->{len} = $len;

		$datum->{value} = $value->[1] if @$value;
	} elsif($tag eq "data") {
		$datum->{type} = "data";
		$datum->{len} = $attrs->{length} if $attrs->{length};

		while(@$value) {
			my($subtag, $subval) = splice(@$value, 0, 2);
			my $item = _xmlnode_to_template($subtag, $subval);
			push @{$datum->{items}}, $item;
		}
	} elsif($tag eq "tlvchain") {
		$datum->{type} = "tlvchain";
		$datum->{subtyped} = 1 if $attrs->{subtyped} and $attrs->{subtyped} eq "yes";

		my($subtag, $subval);

		while(@$value) {
			my($tlvtag, $tlvval) = splice(@$value, 0, 2);
			next if $tlvtag ne "tlv";
			my $tlvattrs = shift @$tlvval;

			my $item = {};
			$item->{type} = "data";
			$item->{name} = $tlvattrs->{name} if $tlvattrs->{name};
			$item->{num} = $tlvattrs->{type};
			$item->{subtype} = $tlvattrs->{subtype} if $tlvattrs->{subtype};
			$item->{items} = [];

			while(@$tlvval) {
				my($subtag, $subval) = splice(@$tlvval, 0, 2);
				next if $subtag eq "0";
				my $tlvitem = _xmlnode_to_template($subtag, $subval);

				push @{$item->{items}}, $tlvitem;
			}

			push @{$datum->{items}}, $item;
		}
	}

	return $datum;
}



our(%PROTOCACHE);
sub protoparse($$) {
	my ($oscar, $wanted) = @_;
	return $PROTOCACHE{$wanted}->set_oscar($oscar) if exists($PROTOCACHE{$wanted});

	my $xml = $xmlmap{$wanted}->{xml} or croak "Couldn't find requested protocol element '$wanted'.";

	confess "No oscar!" unless $oscar;

	my $attrs = shift @$xml;

	my @template = ();

	while(@$xml) {
		my $tag = shift @$xml;
		my $value = shift @$xml;
		next if $tag eq "0";
		push @template, _xmlnode_to_template($tag, $value);
	}

	return @template if $PROTOPARSE_DEBUG;	
	my $obj = Net::OSCAR::XML::Template->new(\@template);
	$PROTOCACHE{$wanted} = $obj;
	return $obj->set_oscar($oscar);
}



# Map a "protobit" (XML <define name="foo">) to SNAC (family, subtype)
sub protobit_to_snacfam($) {
	my $protobit = shift;
	confess "Unknown protobit $protobit" unless $xmlmap{$protobit};
	return ($xmlmap{$protobit}->{family}, $xmlmap{$protobit}->{subtype});
}

# Map a SNAC (family, subtype) to "protobit" (XML <define name="foo">)
sub snacfam_to_protobit($$) {
	my($family, $subtype) = @_;
	if($xml_revmap{$family} and $xml_revmap{$family}->{$subtype}) {
		return $xml_revmap{$family}->{$subtype};
	} elsif($xml_revmap{0} and $xml_revmap{0}->{$subtype}) {
		return $xml_revmap{0}->{$subtype};
	} else {
		return undef;
	}
}

1;
