<?php

declare(strict_types=1);

namespace EduCRM\Services\gateways;

/**
 * SMPP Gateway Implementation
 * Industry-standard SMPP v3.4 protocol for SMS
 * Full PDU implementation without external dependencies
 * 
 * This is a socket-based gateway, not HTTP-based.
 * Extends \EduCRM\Services\MessagingService directly (not AbstractHttpGateway)
 * 
 * @package EduCRM\Services\Gateways
 */

require_once __DIR__ . '/../MessagingService.php';

class SMPPGateway extends \EduCRM\Services\MessagingService
{
    // Gateway metadata
    protected string $gatewayType = 'sms';
    protected array $capabilities = ['sms', 'smpp'];

    // SMPP Command IDs
    const BIND_TRANSMITTER = 0x00000002;
    const BIND_TRANSMITTER_RESP = 0x80000002;
    const SUBMIT_SM = 0x00000004;
    const SUBMIT_SM_RESP = 0x80000004;
    const UNBIND = 0x00000006;
    const UNBIND_RESP = 0x80000006;
    const ENQUIRE_LINK = 0x00000015;
    const ENQUIRE_LINK_RESP = 0x80000015;
    const GENERIC_NACK = 0x80000000;

    // TON (Type of Number)
    const TON_UNKNOWN = 0x00;
    const TON_INTERNATIONAL = 0x01;
    const TON_NATIONAL = 0x02;
    const TON_ALPHANUMERIC = 0x05;

    // NPI (Numbering Plan Indicator)
    const NPI_UNKNOWN = 0x00;
    const NPI_E164 = 0x01;

    private $host;
    private $port;
    private $systemId;
    private $password;
    private $systemType;
    private $sourceAddr;
    private $socket;
    private $sequenceNumber = 1;
    private $timeout = 30;

    public function __construct($pdo, $gateway = null)
    {
        parent::__construct($pdo, $gateway);

        if ($this->config) {
            $this->host = $this->config['host'] ?? 'localhost';
            $this->port = intval($this->config['port'] ?? 2775);
            $this->systemId = $this->config['system_id'] ?? '';
            $this->password = $this->config['password'] ?? '';
            $this->systemType = $this->config['system_type'] ?? '';
            $this->sourceAddr = $this->config['source_addr'] ?? '';
            $this->timeout = intval($this->config['timeout'] ?? 30);
        }
    }

    /**
     * Send SMS via SMPP protocol
     */
    public function send(string $recipient, string $message, array $options = []): array
    {
        $this->log('INFO', "Sending SMS via SMPP", ['recipient' => $recipient]);

        try {
            // Format phone number
            $recipient = $this->formatPhone($recipient);

            // Connect to SMPP server
            if (!$this->connect()) {
                throw new \Exception("Failed to connect to SMPP server");
            }

            // Bind as transmitter
            $bindResult = $this->bindTransmitter();
            if (!$bindResult['success']) {
                $this->disconnect();
                throw new \Exception("Bind failed: " . ($bindResult['error'] ?? 'Unknown error'));
            }

            $this->log('DEBUG', "SMPP bind successful", ['system_id' => $this->systemId]);

            // Submit the message
            $submitResult = $this->submitSm($recipient, $message);
            
            // Unbind gracefully
            $this->unbind();
            $this->disconnect();

            if ($submitResult['success']) {
                $this->log('INFO', "SMS sent successfully via SMPP", [
                    'recipient' => $recipient,
                    'message_id' => $submitResult['message_id']
                ]);
                return [
                    'success' => true,
                    'message_id' => $submitResult['message_id'],
                    'status' => 'sent'
                ];
            } else {
                throw new \Exception($submitResult['error'] ?? 'Submit failed');
            }

        } catch (\Exception $e) {
            $this->log('ERROR', "SMPP send failed: " . $e->getMessage(), [
                'recipient' => $recipient,
                'host' => $this->host,
                'port' => $this->port
            ]);
            $this->disconnect();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Connect to SMPP server
     */
    private function connect()
    {
        $this->log('DEBUG', "Connecting to SMPP server", [
            'host' => $this->host,
            'port' => $this->port
        ]);

        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            $this->log('ERROR', "SMPP connection failed", [
                'errno' => $errno,
                'errstr' => $errstr
            ]);
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);
        return true;
    }

    /**
     * Disconnect from SMPP server
     */
    private function disconnect()
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Bind to SMPP server as transmitter
     */
    private function bindTransmitter()
    {
        // Build bind_transmitter PDU body
        $body = $this->packCString($this->systemId);      // system_id
        $body .= $this->packCString($this->password);     // password
        $body .= $this->packCString($this->systemType);   // system_type
        $body .= pack('C', 0x34);                          // interface_version (3.4)
        $body .= pack('C', self::TON_UNKNOWN);            // addr_ton
        $body .= pack('C', self::NPI_UNKNOWN);            // addr_npi
        $body .= $this->packCString('');                   // address_range

        // Send PDU
        $response = $this->sendPdu(self::BIND_TRANSMITTER, $body);

        if ($response === false) {
            return ['success' => false, 'error' => 'No response from server'];
        }

        // Check response
        if ($response['command_id'] === self::BIND_TRANSMITTER_RESP && $response['command_status'] === 0) {
            return ['success' => true];
        }

        return [
            'success' => false,
            'error' => $this->getStatusMessage($response['command_status'])
        ];
    }

    /**
     * Submit a short message
     */
    private function submitSm($destination, $message)
    {
        // Determine source TON
        $sourceTon = ctype_alpha($this->sourceAddr[0] ?? '') ? self::TON_ALPHANUMERIC : self::TON_INTERNATIONAL;
        
        // Determine destination TON
        $destTon = (substr($destination, 0, 1) === '+') ? self::TON_INTERNATIONAL : self::TON_UNKNOWN;
        $destination = ltrim($destination, '+');

        // Build submit_sm PDU body
        $body = $this->packCString('');                    // service_type
        $body .= pack('C', $sourceTon);                    // source_addr_ton
        $body .= pack('C', self::NPI_E164);               // source_addr_npi
        $body .= $this->packCString($this->sourceAddr);   // source_addr
        $body .= pack('C', $destTon);                      // dest_addr_ton
        $body .= pack('C', self::NPI_E164);               // dest_addr_npi
        $body .= $this->packCString($destination);        // destination_addr
        $body .= pack('C', 0);                             // esm_class
        $body .= pack('C', 0);                             // protocol_id
        $body .= pack('C', 0);                             // priority_flag
        $body .= $this->packCString('');                   // schedule_delivery_time
        $body .= $this->packCString('');                   // validity_period
        $body .= pack('C', 0);                             // registered_delivery
        $body .= pack('C', 0);                             // replace_if_present_flag
        $body .= pack('C', 0);                             // data_coding (GSM 7-bit default)
        $body .= pack('C', 0);                             // sm_default_msg_id
        $body .= pack('C', strlen($message));             // sm_length
        $body .= $message;                                 // short_message

        // Send PDU
        $response = $this->sendPdu(self::SUBMIT_SM, $body);

        if ($response === false) {
            return ['success' => false, 'error' => 'No response from server'];
        }

        // Check response
        if ($response['command_id'] === self::SUBMIT_SM_RESP && $response['command_status'] === 0) {
            // Extract message_id from response body
            $messageId = rtrim($response['body'], "\0");
            return [
                'success' => true,
                'message_id' => $messageId ?: uniqid('smpp_')
            ];
        }

        return [
            'success' => false,
            'error' => $this->getStatusMessage($response['command_status'])
        ];
    }

    /**
     * Unbind from SMPP server
     */
    private function unbind()
    {
        $this->sendPdu(self::UNBIND, '');
        // Don't wait for response, we're disconnecting anyway
    }

    /**
     * Send PDU and receive response
     */
    private function sendPdu($commandId, $body)
    {
        $sequenceNumber = $this->sequenceNumber++;
        
        // Build header (16 bytes)
        $commandLength = 16 + strlen($body);
        $header = pack('N', $commandLength);      // command_length
        $header .= pack('N', $commandId);         // command_id
        $header .= pack('N', 0);                  // command_status (0 for requests)
        $header .= pack('N', $sequenceNumber);   // sequence_number

        $pdu = $header . $body;

        // Send PDU
        $written = fwrite($this->socket, $pdu);
        if ($written === false || $written !== strlen($pdu)) {
            $this->log('ERROR', "Failed to write PDU to socket");
            return false;
        }

        // Read response header (16 bytes)
        $responseHeader = fread($this->socket, 16);
        if (strlen($responseHeader) < 16) {
            $this->log('ERROR', "Incomplete response header received");
            return false;
        }

        $header = unpack('NcommandLength/NcommandId/NcommandStatus/NsequenceNumber', $responseHeader);
        
        // Read response body
        $bodyLength = $header['commandLength'] - 16;
        $responseBody = '';
        if ($bodyLength > 0) {
            $responseBody = fread($this->socket, $bodyLength);
        }

        return [
            'command_length' => $header['commandLength'],
            'command_id' => $header['commandId'],
            'command_status' => $header['commandStatus'],
            'sequence_number' => $header['sequenceNumber'],
            'body' => $responseBody
        ];
    }

    /**
     * Pack a C-style null-terminated string
     */
    private function packCString($string)
    {
        return $string . "\0";
    }

    /**
     * Get human-readable status message
     */
    private function getStatusMessage($status)
    {
        $messages = [
            0x00000000 => 'No Error',
            0x00000001 => 'Message Length is invalid',
            0x00000002 => 'Command Length is invalid',
            0x00000003 => 'Invalid Command ID',
            0x00000004 => 'Incorrect BIND Status for given command',
            0x00000005 => 'ESME Already in Bound State',
            0x00000006 => 'Invalid Priority Flag',
            0x00000007 => 'Invalid Registered Delivery Flag',
            0x00000008 => 'System Error',
            0x0000000A => 'Invalid Source Address',
            0x0000000B => 'Invalid Destination Address',
            0x0000000C => 'Message ID is invalid',
            0x0000000D => 'Bind Failed',
            0x0000000E => 'Invalid Password',
            0x0000000F => 'Invalid System ID',
            0x00000011 => 'Cancel SM Failed',
            0x00000013 => 'Replace SM Failed',
            0x00000014 => 'Message Queue Full',
            0x00000015 => 'Invalid Service Type',
            0x00000033 => 'Invalid number of destinations',
            0x00000034 => 'Invalid Distribution List name',
            0x00000040 => 'Destination flag is invalid',
            0x00000042 => 'Invalid submit with replace request',
            0x00000043 => 'Invalid esm_class field data',
            0x00000044 => 'Cannot Submit to Distribution List',
            0x00000045 => 'submit_sm or submit_multi failed',
            0x00000048 => 'Invalid Source address TON',
            0x00000049 => 'Invalid Source address NPI',
            0x00000050 => 'Invalid Destination address TON',
            0x00000051 => 'Invalid Destination address NPI',
            0x00000053 => 'Invalid system_type field',
            0x00000054 => 'Invalid replace_if_present flag',
            0x00000055 => 'Invalid number of messages',
            0x00000058 => 'Throttling error',
            0x00000061 => 'Invalid Scheduled Delivery Time',
            0x00000062 => 'Invalid message validity period',
            0x00000063 => 'Predefined Message Invalid or Not Found',
            0x00000064 => 'ESME Receiver Temporary App Error Code',
            0x00000065 => 'ESME Receiver Permanent App Error Code',
            0x00000066 => 'ESME Receiver Reject Message Error Code',
            0x00000067 => 'query_sm request failed',
            0x000000C0 => 'Error in the optional part of the PDU Body',
            0x000000C1 => 'Optional Parameter not allowed',
            0x000000C2 => 'Invalid Parameter Length',
            0x000000C3 => 'Expected Optional Parameter missing',
            0x000000C4 => 'Invalid Optional Parameter Value',
            0x000000FE => 'Delivery Failure (used for data_sm_resp)',
            0x000000FF => 'Unknown Error',
        ];

        return $messages[$status] ?? "Unknown status code: 0x" . dechex($status);
    }

    /**
     * Get message status
     */
    public function getStatus(string $messageId): array
    {
        // SMPP delivery receipts would be handled via a receiver bind
        // For now, return unknown status
        $this->log('DEBUG', "Status query for SMPP message", ['message_id' => $messageId]);
        return [
            'status' => 'sent',
            'delivered_at' => null,
            'error' => 'Delivery status requires SMPP receiver/transceiver bind with DLR'
        ];
    }

    /**
     * Test SMPP connection
     */
    public function testConnection(): bool
    {
        $this->log('INFO', "Testing SMPP connection", [
            'host' => $this->host,
            'port' => $this->port
        ]);

        try {
            if (!$this->connect()) {
                $this->log('ERROR', "SMPP connection test failed - cannot connect");
                return false;
            }

            $bindResult = $this->bindTransmitter();
            
            if ($bindResult['success']) {
                $this->unbind();
                $this->disconnect();
                $this->log('INFO', "SMPP connection test successful");
                return true;
            }

            $this->disconnect();
            $this->log('ERROR', "SMPP bind test failed", ['error' => $bindResult['error'] ?? 'Unknown']);
            return false;

        } catch (\Exception $e) {
            $this->log('ERROR', "SMPP connection test exception: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }
}
