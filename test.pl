#!/usr/bin/perl
use strict;
use warnings;
use CGI qw(:standard);
use JSON;
use POSIX qw(strftime);
use DBI;
use Data::Dumper;


print "Content-Type: application/json\n\n";

my $method = uc($ENV{'REQUEST_METHOD'} || '');
my $buffer = '';
my %params;


my $timestamp = strftime("%Y-%m-%d %H:%M:%S", localtime);
my $cur_date = strftime("%Y-%m-%d", localtime);

open(my $LOG, ">>", "/tmp/post_li_valid_data_ping_$cur_date.log")
    or die "Cannot open log file: $!";


if ($method eq 'POST')
{
    read(STDIN, $buffer, $ENV{'CONTENT_LENGTH'} || 0);
    my $content_type = $ENV{'CONTENT_TYPE'} || '';

    if ($content_type =~ m|application/json|i) {
        eval {
            my $json_data = decode_json($buffer);
            %params = %{$json_data};
        };
        if ($@) {
            print $LOG "$timestamp - ERROR: Invalid JSON body: $buffer\n";
            print encode_json({status  => 'error',message => 'Invalid JSON body',received => $buffer});
            close $LOG;
            exit;
        }
    } else {
        %params = map { split /=/, $_, 2 } split /&/, $buffer;
    }
} else {
    %params = map { split /=/, $_, 2 } split /&/, ($ENV{'QUERY_STRING'} || '');
}

print $LOG "$timestamp - Received params: " . Dumper(\%params);


unless (exists $params{'email'} && exists $params{'offer'}) {
    print $LOG "$timestamp - Missing required fields\n";
    print encode_json({status   => 'error',message  => 'Required fields missing',received => \%params});
    close $LOG;
    exit;
}

if ($params{'email'} !~ /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/) {
    print $LOG "$timestamp - Invalid email format: $params{'email'}\n";
    print encode_json({ status => 'error', message => 'Invalid email format', received => \%params });
    close $LOG;
    exit;
}

my $dsn = "DBI:mysql:database=mt2_data;host=cmprep-prod-vip.bo3.e-dialog.com";
my $username = "mt_report_rw";
my $password = "jY9escB%DuAw";
my $dbh = DBI->connect($dsn, $username, $password, { RaiseError => 1, PrintError => 0, mysql_enable_utf8 => 1 });

if (!$dbh) {
    print $LOG "- Error: Database connection failed.\n";
    print JSON->new->allow_nonref->encode({ status => "error", message => "Connection failed" });
    close($LOG);
    exit(0);
}

eval {
    my $sql = "INSERT INTO mt2_reports.offer_based_email_mappings (email, offer_id, created_at, updated_at)
               VALUES (?, ?, NOW(), NOW())";
    my $sth = $dbh->prepare($sql);
    $sth->execute($params{'email'}, $params{'offer'});

    print $LOG "$timestamp - SUCCESS: Inserted email=$params{'email'}, offer_id=$params{'offer'}\n";
};
if ($@) {
    print $LOG "$timestamp - ERROR: DB insert failed: $@\n";
    print encode_json({ status => 'error', message => 'Connection failed' ,raw => \%params});
    $dbh->disconnect;
    close $LOG;
    exit;
}

$dbh->disconnect;
close $LOG;

print encode_json({ status => 'success', message => 'Record received', raw => \%params });
