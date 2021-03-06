<?php

class Scribunto_LuaCommonTests extends Scribunto_LuaEngineTestBase {
	protected static $moduleName = 'CommonTests';

	private static $allowedGlobals = array(
		// Functions
		'assert',
		'error',
		'getfenv',
		'getmetatable',
		'ipairs',
		'next',
		'pairs',
		'pcall',
		'rawequal',
		'rawget',
		'rawset',
		'require',
		'select',
		'setfenv',
		'setmetatable',
		'tonumber',
		'tostring',
		'type',
		'unpack',
		'xpcall',

		// Packages
		'_G',
		'debug',
		'math',
		'mw',
		'os',
		'package',
		'string',
		'table',

		// Misc
		'_VERSION',
	);

	protected function setUp() {
		parent::setUp();

		// Register libraries for self::testPHPLibrary()
		$this->mergeMwGlobalArrayValue( 'wgHooks', array(
			'ScribuntoExternalLibraries' => array(
				function ( $engine, &$libs ) {
					$libs += array(
						'CommonTestsLib' => array(
							'class' => 'Scribunto_LuaCommonTestsLibrary',
							'deferLoad' => true,
						),
						'CommonTestsFailLib' => array(
							'class' => 'Scribunto_LuaCommonTestsFailLibrary',
							'deferLoad' => true,
						),
					);
				}
			)
		) );

		// Note this depends on every iteration of the data provider running with a clean parser
		$this->getEngine()->getParser()->getOptions()->setExpensiveParserFunctionLimit( 10 );

		// Some of the tests need this
		$interpreter = $this->getEngine()->getInterpreter();
		$interpreter->callFunction(
			$interpreter->loadString( 'mw.makeProtectedEnvFuncsForTest = mw.makeProtectedEnvFuncs', 'fortest' )
		);
	}

	protected function getTestModules() {
		return parent::getTestModules() + array(
			'CommonTests' => __DIR__ . '/CommonTests.lua',
			'CommonTests-data' => __DIR__ . '/CommonTests-data.lua',
			'CommonTests-data-fail1' => __DIR__ . '/CommonTests-data-fail1.lua',
			'CommonTests-data-fail2' => __DIR__ . '/CommonTests-data-fail2.lua',
			'CommonTests-data-fail3' => __DIR__ . '/CommonTests-data-fail3.lua',
			'CommonTests-data-fail4' => __DIR__ . '/CommonTests-data-fail4.lua',
			'CommonTests-data-fail5' => __DIR__ . '/CommonTests-data-fail5.lua',
		);
	}

	public function testNoLeakedGlobals() {
		$interpreter = $this->getEngine()->getInterpreter();

		list( $actualGlobals ) = $interpreter->callFunction(
			$interpreter->loadString(
				'local t = {} for k in pairs( _G ) do t[#t+1] = k end return t',
				'getglobals'
			)
		);

		$leakedGlobals = array_diff( $actualGlobals, self::$allowedGlobals );
		$this->assertEquals( 0, count( $leakedGlobals ),
			'The following globals are leaked: ' . join( ' ', $leakedGlobals )
		);
	}

	public function testPHPLibrary() {
		$engine = $this->getEngine();
		$frame = $engine->getParser()->getPreprocessor()->newFrame();

		$title = Title::makeTitle( NS_MODULE, 'TestInfoPassViaPHPLibrary' );
		$this->extraModules[$title->getFullText()] = '
			local p = {}

			function p.test()
				local lib = require( "CommonTestsLib" )
				return table.concat( { lib.test() }, "; " )
			end

			function p.setVal( frame )
				local lib = require( "CommonTestsLib" )
				lib.val = frame.args[1]
				lib.foobar.val = frame.args[1]
			end

			function p.getVal()
				local lib = require( "CommonTestsLib" )
				return tostring( lib.val ), tostring( lib.foobar.val )
			end

			function p.getSetVal( frame )
				p.setVal( frame )
				return p.getVal()
			end

			function p.checkPackage()
				local ret = {}
				ret[1] = package.loaded["CommonTestsLib"] == nil
				require( "CommonTestsLib" )
				ret[2] = package.loaded["CommonTestsLib"] ~= nil
				return ret[1], ret[2]
			end

			function p.libSetVal( frame )
				local lib = require( "CommonTestsLib" )
				return lib.setVal( frame )
			end

			function p.libGetVal()
				local lib = require( "CommonTestsLib" )
				return lib.getVal()
			end

			return p
		';

		# Test loading
		$module = $engine->fetchModuleFromParser( $title );
		$ret = $module->invoke( 'test', $frame->newChild() );
		$this->assertSame( 'Test option; Test function', $ret,
			'Library can be loaded and called' );

		# Test package.loaded
		$module = $engine->fetchModuleFromParser( $title );
		$ret = $module->invoke( 'checkPackage', $frame->newChild() );
		$this->assertSame( 'truetrue', $ret,
			'package.loaded is right on the first call' );
		$ret = $module->invoke( 'checkPackage', $frame->newChild() );
		$this->assertSame( 'truetrue', $ret,
			'package.loaded is right on the second call' );

		# Test caching for require
		$args = $engine->getParser()->getPreprocessor()->newPartNodeArray( array( 1 => 'cached' ) );
		$ret = $module->invoke( 'getSetVal', $frame->newChild( $args ) );
		$this->assertSame( 'cachedcached', $ret,
			'same loaded table is returned by multiple require calls' );

		# Test no data communication between invokes
		$module = $engine->fetchModuleFromParser( $title );
		$args = $engine->getParser()->getPreprocessor()->newPartNodeArray( array( 1 => 'fail' ) );
		$module->invoke( 'setVal', $frame->newChild( $args ) );
		$ret = $module->invoke( 'getVal', $frame->newChild() );
		$this->assertSame( 'nilnope', $ret,
			'same loaded table is not shared between invokes' );

		# Test that the library isn't being recreated between invokes
		$module = $engine->fetchModuleFromParser( $title );
		$ret = $module->invoke( 'libGetVal', $frame->newChild() );
		$this->assertSame( 'nil', $ret, 'sanity check' );
		$args = $engine->getParser()->getPreprocessor()->newPartNodeArray( array( 1 => 'ok' ) );
		$module->invoke( 'libSetVal', $frame->newChild( $args ) );

		$module = $engine->fetchModuleFromParser( $title );
		$ret = $module->invoke( 'libGetVal', $frame->newChild() );
		$this->assertSame( 'ok', $ret,
			'library is not recreated between invokes' );
	}

	public function testModuleStringExtend() {
		$engine = $this->getEngine();
		$interpreter = $engine->getInterpreter();

		$interpreter->callFunction(
			$interpreter->loadString( 'string.testModuleStringExtend = "ok"', 'extendstring' )
		);
		$ret = $interpreter->callFunction(
			$interpreter->loadString( 'return ("").testModuleStringExtend', 'teststring1' )
		);
		$this->assertSame( array( 'ok' ), $ret, 'string can be extended' );

		$this->extraModules['Module:testModuleStringExtend'] = '
			return {
				test = function() return ("").testModuleStringExtend end
			}
			';
		$module = $engine->fetchModuleFromParser(
			Title::makeTitle( NS_MODULE, 'testModuleStringExtend' )
		);
		$ret = $interpreter->callFunction( $engine->executeModule( $module->getInitChunk(), 'test', null ) );
		$this->assertSame( array( 'ok' ), $ret, 'string extension can be used from module' );

		$this->extraModules['Module:testModuleStringExtend2'] = '
			return {
				test = function()
					string.testModuleStringExtend = "fail"
					return ("").testModuleStringExtend
				end
			}
			';
		$module = $engine->fetchModuleFromParser(
			Title::makeTitle( NS_MODULE, 'testModuleStringExtend2' )
		);
		$ret = $interpreter->callFunction( $engine->executeModule( $module->getInitChunk(), 'test', null ) );
		$this->assertSame( array( 'ok' ), $ret, 'string extension cannot be modified from module' );
		$ret = $interpreter->callFunction(
			$interpreter->loadString( 'return string.testModuleStringExtend', 'teststring2' )
		);
		$this->assertSame( array( 'ok' ), $ret, 'string extension cannot be modified from module' );

		$ret = $engine->runConsole( array(
			'prevQuestions' => array(),
			'question' => '=("").testModuleStringExtend',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( 'ok', $ret['return'], 'string extension can be used from console' );

		$ret = $engine->runConsole( array(
			'prevQuestions' => array( 'string.fail = "fail"' ),
			'question' => '=("").fail',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( 'nil', $ret['return'], 'string cannot be extended from console' );

		$ret = $engine->runConsole( array(
			'prevQuestions' => array( 'string.testModuleStringExtend = "fail"' ),
			'question' => '=("").testModuleStringExtend',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( 'ok', $ret['return'], 'string extension cannot be modified from console' );
		$ret = $interpreter->callFunction(
			$interpreter->loadString( 'return string.testModuleStringExtend', 'teststring3' )
		);
		$this->assertSame( array( 'ok' ), $ret, 'string extension cannot be modified from console' );

		$interpreter->callFunction(
			$interpreter->loadString( 'string.testModuleStringExtend = nil', 'unextendstring' )
		);
	}

	public function testLoadDataLoadedOnce() {
		$engine = $this->getEngine();
		$interpreter = $engine->getInterpreter();
		$frame = $engine->getParser()->getPreprocessor()->newFrame();

		$loadcount = 0;
		$interpreter->callFunction(
			$interpreter->loadString( 'mw.markLoaded = ...', 'fortest' ),
			$interpreter->wrapPHPFunction( function () use (&$loadcount) {
				$loadcount++;
			} )
		);
		$this->extraModules['Module:TestLoadDataLoadedOnce-data'] = '
			mw.markLoaded()
			return {}
		';
		$this->extraModules['Module:TestLoadDataLoadedOnce'] = '
			local data = mw.loadData( "Module:TestLoadDataLoadedOnce-data" )
			return {
				foo = function() end,
				bar = function()
					return tostring( package.loaded["Module:TestLoadDataLoadedOnce-data"] )
				end,
			}
		';

		// Make sure data module isn't parsed twice. Simulate several {{#invoke:}}s
		$title = Title::makeTitle( NS_MODULE, 'TestLoadDataLoadedOnce' );
		for ( $i = 0; $i < 10; $i++ ) {
			$module = $engine->fetchModuleFromParser( $title );
			$module->invoke( 'foo', $frame->newChild() );
		}
		$this->assertSame( 1, $loadcount, 'data module was loaded more than once' );

		// Make sure data module isn't in package.loaded
		$this->assertSame( 'nil', $module->invoke( 'bar', $frame ),
			'data module was stored in module\'s package.loaded'
		);
		$this->assertSame( array( 'nil' ),
			$interpreter->callFunction( $interpreter->loadString(
				'return tostring( package.loaded["Module:TestLoadDataLoadedOnce-data"] )', 'getLoaded'
			) ),
			'data module was stored in top level\'s package.loaded'
		);
	}

	public function testFrames() {
		$engine = $this->getEngine();

		$ret = $engine->runConsole( array(
			'prevQuestions' => array(),
			'question' => '=mw.getCurrentFrame()',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( 'table', $ret['return'], 'frames can be used in the console' );

		$ret = $engine->runConsole( array(
			'prevQuestions' => array(),
			'question' => '=mw.getCurrentFrame():newChild{}',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( 'table', $ret['return'], 'child frames can be created' );

		$ret = $engine->runConsole( array(
			'prevQuestions' => array(
				'f = mw.getCurrentFrame():newChild{ args = { "ok" } }',
				'f2 = f:newChild{ args = {} }'
			),
			'question' => '=f2:getParent().args[1], f2:getParent():getParent()',
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		) );
		$this->assertSame( "ok\ttable", $ret['return'], 'child frames have correct parents' );
	}

	public function testCallParserFunction() {
		global $wgContLang;

		$engine = $this->getEngine();
		$parser = $engine->getParser();

		$args = array(
			'prevQuestions' => array(),
			'content' => 'return {}',
			'title' => Title::makeTitle( NS_MODULE, 'dummy' ),
		);

		// Test argument calling conventions
		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction{
				name = "urlencode", args = { "x x", "wiki" }
			}',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (named args w/table)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction{
				name = "urlencode", args = "x x"
			}',
		) + $args );
		$this->assertSame( "x+x", $ret['return'],
			'callParserFunction works for {{urlencode:x x}} (named args w/scalar)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction( "urlencode", { "x x", "wiki" } )',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (positional args w/table)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction( "urlencode", "x x", "wiki" )',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (positional args w/scalars)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction{
				name = "urlencode:x x", args = { "wiki" }
			}',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (colon in name, named args w/table)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction{
				name = "urlencode:x x", args = "wiki"
			}',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (colon in name, named args w/scalar)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction( "urlencode:x x", { "wiki" } )',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (colon in name, positional args w/table)'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction( "urlencode:x x", "wiki" )',
		) + $args );
		$this->assertSame( "x_x", $ret['return'],
			'callParserFunction works for {{urlencode:x x|wiki}} (colon in name, positional args w/scalars)'
		);

		// Test named args to the parser function
		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():callParserFunction( "#tag:pre",
				{ "foo", style = "margin-left: 1.6em" }
			)',
		) + $args );
		$this->assertSame(
			'<pre style="margin-left: 1.6em">foo</pre>',
			$parser->mStripState->unstripBoth( $ret['return'] ),
			'callParserFunction works for {{#tag:pre|foo|style=margin-left: 1.6em}}'
		);

		// Test extensionTag
		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():extensionTag( "pre", "foo",
				{ style = "margin-left: 1.6em" }
			)',
		) + $args );
		$this->assertSame(
			'<pre style="margin-left: 1.6em">foo</pre>',
			$parser->mStripState->unstripBoth( $ret['return'] ),
			'extensionTag works for {{#tag:pre|foo|style=margin-left: 1.6em}}'
		);

		$ret = $engine->runConsole( array(
			'question' => '=mw.getCurrentFrame():extensionTag{ name = "pre", content = "foo",
				args = { style = "margin-left: 1.6em" }
			}',
		) + $args );
		$this->assertSame(
			'<pre style="margin-left: 1.6em">foo</pre>',
			$parser->mStripState->unstripBoth( $ret['return'] ),
			'extensionTag works for {{#tag:pre|foo|style=margin-left: 1.6em}}'
		);

		// Test calling a non-existent function
		try {
			$ret = $engine->runConsole( array(
				'question' => '=mw.getCurrentFrame():callParserFunction{
					name = "thisDoesNotExist", args = { "" }
				}',
			) + $args );
			$this->fail( "Expected LuaError not thrown for nonexistent parser function" );
		} catch ( Scribunto_LuaError $err ) {
			$this->assertSame(
				'Lua error: callParserFunction: function "thisDoesNotExist" was not found.',
				$err->getMessage(),
				'callParserFunction correctly errors for nonexistent function'
			);
		}
	}

	public function testBug62291() {
		$engine = $this->getEngine();
		$frame = $engine->getParser()->getPreprocessor()->newFrame();

		$this->extraModules['Module:Bug62291'] = '
			local p = {}
			function p.foo()
				return table.concat( {
					math.random(), math.random(), math.random(), math.random(), math.random()
				}, ", " )
			end
			function p.bar()
				local t = {}
				t[1] = p.foo()
				t[2] = mw.getCurrentFrame():preprocess( "{{#invoke:Bug62291|bar2}}" )
				t[3] = p.foo()
				return table.concat( t, "; " )
			end
			function p.bar2()
				return "bar2 called"
			end
			return p
		';

		$title = Title::makeTitle( NS_MODULE, 'Bug62291' );
		$module = $engine->fetchModuleFromParser( $title );

		// Make sure multiple invokes return the same text
		$r1 = $module->invoke( 'foo', $frame->newChild() );
		$r2 = $module->invoke( 'foo', $frame->newChild() );
		$this->assertSame( $r1, $r2, 'Multiple invokes returned different sets of random numbers' );

		// Make sure a recursive invoke doesn't reset the PRNG
		$r1 = $module->invoke( 'bar', $frame->newChild() );
		$r = explode( '; ', $r1 );
		$this->assertNotSame( $r[0], $r[2], 'Recursive invoke reset PRNG' );
		$this->assertSame( 'bar2 called', $r[1], 'Sanity check failed' );

		// But a second invoke does
		$r2 = $module->invoke( 'bar', $frame->newChild() );
		$this->assertSame( $r1, $r2, 'Multiple invokes with recursive invoke returned different sets of random numbers' );
	}

	public function testOsDateTimeTTLs() {
		$engine = $this->getEngine();
		$pp = $engine->getParser()->getPreprocessor();

		if ( !is_callable( array( $pp->newFrame(), 'getTTL' ) ) ) {
			$this->markTestSkipped( "PPFrame::getTTL is not available" );
		}

		$this->extraModules['Module:DateTime'] = '
		local p = {}
		function p.day()
			return os.date( "%d" )
		end
		function p.AMPM()
			return os.date( "%p" )
		end
		function p.hour()
			return os.date( "%H" )
		end
		function p.minute()
			return os.date( "%M" )
		end
		function p.second()
			return os.date( "%S" )
		end
		function p.table()
			return os.date( "*t" )
		end
		function p.tablesec()
			return os.date( "*t" ).sec
		end
		function p.time()
			return os.time()
		end
		function p.specificDateAndTime()
			return os.date("%S", os.time{year = 2013, month = 1, day = 1})
		end
		return p
		';

		$title = Title::makeTitle( NS_MODULE, 'DateTime' );
		$module = $engine->fetchModuleFromParser( $title );

		$frame = $pp->newFrame();
		$module->invoke( 'day', $frame );
		$this->assertNotNull( $frame->getTTL(), 'TTL must be set when day is requested' );
		$this->assertLessThanOrEqual( 86400, $frame->getTTL(), 'TTL must not exceed 1 day when day is requested' );

		$frame = $pp->newFrame();
		$module->invoke( 'AMPM', $frame );
		$this->assertNotNull( $frame->getTTL(), 'TTL must be set when AM/PM is requested' );
		$this->assertLessThanOrEqual( 43200, $frame->getTTL(), 'TTL must not exceed 12 hours when AM/PM is requested' );

		$frame = $pp->newFrame();
		$module->invoke( 'hour', $frame );
		$this->assertNotNull( $frame->getTTL(), 'TTL must be set when hour is requested' );
		$this->assertLessThanOrEqual( 3600, $frame->getTTL(), 'TTL must not exceed 1 hour when hours are requested' );

		$frame = $pp->newFrame();
		$module->invoke( 'minute', $frame );
		$this->assertNotNull( $frame->getTTL(), 'TTL must be set when minutes are requested' );
		$this->assertLessThanOrEqual( 60, $frame->getTTL(), 'TTL must not exceed 1 minute when minutes are requested' );

		$frame = $pp->newFrame();
		$module->invoke( 'second', $frame );
		$this->assertEquals( 1, $frame->getTTL(), 'TTL must be equal to 1 second when seconds are requested' );

		$frame = $pp->newFrame();
		$module->invoke( 'table', $frame );
		$this->assertNull( $frame->getTTL(), 'TTL must not be set when os.date( "*t" ) is called but no values are looked at' );

		$frame = $pp->newFrame();
		$module->invoke( 'tablesec', $frame );
		$this->assertEquals( 1, $frame->getTTL(), 'TTL must be equal to 1 second when seconds are requested from a table' );

		$frame = $pp->newFrame();
		$module->invoke( 'time', $frame );
		$this->assertEquals( 1, $frame->getTTL(), 'TTL must be equal to 1 second when os.time() is called' );

		$frame = $pp->newFrame();
		$module->invoke( 'specificDateAndTime', $frame );
		$this->assertNull( $frame->getTTL(), 'TTL must not be set when os.date() or os.time() are called with a specific time' );
	}

	/**
	 * @dataProvider provideVolatileCaching
	 */
	public function testVolatileCaching( $func ) {
		$engine = $this->getEngine();
		$parser = $engine->getParser();
		$pp = $parser->getPreprocessor();

		if ( !is_callable( array( $pp->newFrame(), 'isVolatile' ) ) ) {
			$this->markTestSkipped( "PPFrame::isVolatile is not available" );
		}

		$count = 0;
		$parser->setHook( 'scribuntocount', function ( $str, $argv, $parser, $frame ) use ( &$count ) {
			$frame->setVolatile();
			return ++$count;
		} );

		$this->extraModules['Template:ScribuntoTestVolatileCaching'] = '<scribuntocount/>';
		$this->extraModules['Module:TestVolatileCaching'] = '
			return {
				preprocess = function ( frame )
					return frame:preprocess( "<scribuntocount/>" )
				end,
				extensionTag = function ( frame )
					return frame:extensionTag( "scribuntocount" )
				end,
				expandTemplate = function ( frame )
					return frame:expandTemplate{ title = "ScribuntoTestVolatileCaching" }
				end,
			}
		';

		$frame = $pp->newFrame();
		$count = 0;
		$wikitext = "{{#invoke:TestVolatileCaching|$func}}";
		$text = $frame->expand( $pp->preprocessToObj( "$wikitext $wikitext" ) );
		$text = $parser->mStripState->unstripBoth( $text );
		$this->assertTrue( $frame->isVolatile(), "Frame is marked volatile" );
		$this->assertEquals( '1 2', $text, "Volatile wikitext was not cached" );
	}

	public function provideVolatileCaching() {
		return array(
			array( 'preprocess' ),
			array( 'extensionTag' ),
			array( 'expandTemplate' ),
		);
	}

	public function testGetCurrentFrameAndMWLoadData() {
		$engine = $this->getEngine();
		$parser = $engine->getParser();
		$pp = $parser->getPreprocessor();

		$this->extraModules['Module:Bug65687'] = '
			return {
				test = function ( frame )
					return mw.loadData( "Module:Bug65687-LD" )[1]
				end
			}
		';
		$this->extraModules['Module:Bug65687-LD'] = 'return { mw.getCurrentFrame().args[1] or "ok" }';

		$frame = $pp->newFrame();
		$text = $frame->expand( $pp->preprocessToObj( "{{#invoke:Bug65687|test|foo}}" ) );
		$text = $parser->mStripState->unstripBoth( $text );
		$this->assertEquals( 'ok', $text, 'mw.loadData allowed access to frame args' );
	}

	public function testGetCurrentFrameAtModuleScope() {
		$engine = $this->getEngine();
		$parser = $engine->getParser();
		$pp = $parser->getPreprocessor();

		$this->extraModules['Module:Bug67498-directly'] = '
			local f = mw.getCurrentFrame()
			local f2 = f and f.args[1] or "<none>"

			return {
				test = function ( frame )
					return ( f and f.args[1] or "<none>" ) .. " " .. f2
				end
			}
		';
		$this->extraModules['Module:Bug67498-statically'] = '
			local M = require( "Module:Bug67498-directly" )
			return {
				test = function ( frame )
					return M.test( frame )
				end
			}
		';
		$this->extraModules['Module:Bug67498-dynamically'] = '
			return {
				test = function ( frame )
					local M = require( "Module:Bug67498-directly" )
					return M.test( frame )
				end
			}
		';

		foreach ( array( 'directly', 'statically', 'dynamically' ) as $how ) {
			$frame = $pp->newFrame();
			$text = $frame->expand( $pp->preprocessToObj(
				"{{#invoke:Bug67498-$how|test|foo}} -- {{#invoke:Bug67498-$how|test|bar}}"
			) );
			$text = $parser->mStripState->unstripBoth( $text );
			$text = explode( ' -- ', $text );
			$this->assertEquals( 'foo foo', $text[0],
				"mw.getCurrentFrame() failed from a module loaded $how"
			);
			$this->assertEquals( 'bar bar', $text[1],
				"mw.getCurrentFrame() cached the frame from a module loaded $how"
			);
		}
	}
}

class Scribunto_LuaCommonTestsLibrary extends Scribunto_LuaLibraryBase {
	public function register() {
		$lib = array(
			'test' => array( $this, 'test' ),
		);
		$opts = array(
			'test' => 'Test option',
		);

		return $this->getEngine()->registerInterface( __DIR__ . '/CommonTests-lib.lua', $lib, $opts );
	}

	public function test() {
		return array( 'Test function' );
	}
}

class Scribunto_LuaCommonTestsFailLibrary extends Scribunto_LuaLibraryBase {
	public function __construct() {
		throw new MWException( 'deferLoad library that is never required was loaded anyway' );
	}

	public function register() {
	}
}
