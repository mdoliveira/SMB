<?php

namespace SMB\Test;

class NativeStream extends \PHPUnit_Framework_TestCase {
	/**
	 * @var \Icewind\SMB\Server $server
	 */
	protected $server;

	/**
	 * @var \Icewind\SMB\NativeShare $share
	 */
	protected $share;

	/**
	 * @var string $root
	 */
	protected $root;

	protected $config;

	public function setUp() {
		if (!function_exists('smbclient_state_new')) {
			$this->markTestSkipped('libsmbclient php extension not installed');
		}
		$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));
		$this->server = new \Icewind\SMB\NativeServer($this->config->host, $this->config->user, $this->config->password);
		$this->share = new \Icewind\SMB\NativeShare($this->server, $this->config->share);
		if ($this->config->root) {
			$this->root = '/' . $this->config->root . '/' . uniqid();
		} else {
			$this->root = '/' . uniqid();
		}
		$this->share->mkdir($this->root);
	}

	private function getTextFile() {
		$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua';
		$file = tempnam('/tmp', 'smb_test_');
		file_put_contents($file, $text);
		return $file;
	}

	public function testSeekTell() {
		$sourceFile = $this->getTextFile();
		$this->share->put($sourceFile, $this->root . '/foobar');
		$fh = $this->share->read($this->root . '/foobar');
		$content = fread($fh, 3);
		$this->assertEquals('Lor', $content);

		fseek($fh, -2, SEEK_CUR);

		$content = fread($fh, 3);
		$this->assertEquals('ore', $content);

		fseek($fh, 3, SEEK_SET);

		$content = fread($fh, 3);
		$this->assertEquals('em ', $content);

		fseek($fh, -3, SEEK_END);

		$content = fread($fh, 3);
		$this->assertEquals('qua', $content);

		fseek($fh, -3, SEEK_END);
		$this->assertEquals(120, ftell($fh));
	}

	public function testWrite() {
		$fh = $this->share->write($this->root . '/foobar');
		fwrite($fh, 'qwerty');
		fclose($fh);

		$tmpFile1 = tempnam('/tmp', 'smb_test_');
		$this->share->get($this->root . '/foobar', $tmpFile1);
		$this->assertEquals('qwerty', file_get_contents($tmpFile1));
		unlink($tmpFile1);
	}

	public function tearDown() {
		if ($this->share) {
			$this->cleanDir($this->root);
		}
		unset($this->share);
	}

	public function cleanDir($dir) {
		$content = $this->share->dir($dir);
		foreach ($content as $name => $metadata) {
			if ($metadata['type'] === 'dir') {
				$this->cleanDir($dir . '/' . $name);
			} else {
				$this->share->del($dir . '/' . $name);
			}
		}
		$this->share->rmdir($dir);
	}
}
