<?php 

	$CPU = new DCPU16();

	$ASM = file_get_contents('hello.dasm');
	echo $CPU->assemble($ASM);

	class DCPU16 {
		protected $REGISTERS = array('A', 'B', 'C', 'X', 'Y', 'Z', 'I', 'J');
		protected $SPECIAL_REGISTERS = array('POP', 'SEEK', 'PUSH', 'SP', 'PC', 'O');
		protected $OPCODES = array(NULL, 'SET', 'ADD', 'SUB', 'MUL', 'DIV', 'MOD', 'SHL', 'SHR', 'AND', 'BOR', 'XOR', 'IFE', 'IFN', 'IFG', 'IFB');

		public function assemble($sInput) {
			$arOutput = array();
			$arLines = preg_split("/\n/", $sInput);
			$arLinePositions = array();
			$arLabels = array();
			$iCurrentLine = 0;
			$iSkippedLines = 0;

			foreach($arLines as $lineNum => $line) {
				$arLinePositions[] = $lineNum;

				$line = strtoupper($line);
				$line = preg_replace('/^\s+|\s+$/', '', $line); // Whitespace
				$line = preg_replace('/\s*;.*/', '', $line); // Comments

				if($line === '') {
					$iSkippedLines++;
				}else{
					$lineParts = preg_split('/,?\s+/', $line);
					if($lineParts[0][0] === ':') {
						$arLabels[$lineParts[0]] = $lineNum;
						array_shift($lineParts);
					}

					$opCode = $lineParts[0];
					$arValues = array_slice($lineParts, 1);
					$arBytes = array();
					$opByte = 0;

					if(in_array($opCode, $this->OPCODES)) {
						$opByte = array_search($opCode, $this->OPCODES);
					}elseif($opCode === 'JSR') {
						array_unshift($arValues, 0x01);
					}elseif($opCode == 'DAT') {
						/*$nextChar = "";
						$iP = 1;
						
						$printString = join(" ", $arValues);
						$printLen = strlen($printString) - 1;
						
						do {
							if($iP < $printLen) {
								$nextChar = $printString[$iP];
								echo $nextChar;
							}
							$iP++;
						} while($iP < $printLen);

						continue;*/
					}else{
						trigger_error("Unknown operation: {$opCode}");
					}

					foreach ($arValues as $i => $val) {
						$iPos = 6 * $i + 4;

						if(is_numeric($val)) {
							if(intval($val, 16) <= 0x1f) {
								$opByte += ((intval($val, 16) + 0x20) << $iPos);
							}else{
								$opByte += 0x1f << $iPos;
								$arBytes[] = intval($val, 16);
							}
						}elseif(in_array($val, $this->REGISTERS)) {
							$opByte += array_search($val, $this->REGISTERS) << $iPos;
						}elseif(in_array($val, $this->SPECIAL_REGISTERS)) {
							$opByte += (0x18 + array_search($val, $this->SPECIAL_REGISTERS)) << $iPos;
						}elseif($val[0] === '[' && substr($val, -1) === ']') {
							$memLoc = substr($val, 1, -1);
							if(strpos($memLoc, '+')) {
								$locParts = explode('+', $memLoc);
								$opByte += (0x10 + array_search($locParts[1], $this->REGISTERS) << $iPos);
								$arBytes[] = intval($memLoc, 16);
							}elseif(is_numeric($memLoc)) {
								$opByte += 0x1e << $iPos;
								$arBytes[] = intval($memLoc, 16);
							}elseif(in_array($memLoc, $this->REGISTERS)) {
								$opByte += (0x08 + array_search($memLoc, $this->REGISTERS) << $iPos);
							}
						}elseif($i == 1){
							$opByte += 0x1f << $iPos;
							$arBytes[] = ':' . $val;
						}else{
							trigger_error("Invalid value: {$val}");
						}
					}

					array_unshift($arBytes, $opByte);
					$arOutput[] = $arBytes;
					$iCurrentLine += count($arBytes);
				}
			}

			$arProcessed = array();
			foreach($arOutput as $i => $line) {
				$arNewLine = array();
				foreach($line as $byte) {
					if($byte[0] === ':') {
						if($arLinePositions[$arLabels[$byte]]) {
							$arNewLine[] = $arLinePositions[$arLabels[$byte]];
						} else {
							$lineNum = $i + 1;
							trigger_error("Line not found: {$byte} (#{$lineNum})");
						}
					}else{
						$arNewLine[] = $byte;
					}
				}
				$arProcessed[] = $arNewLine;
			}

			$sOutput = '';

			$sOutput = join('<br />', array_map(function($bytes) {
				return join(' ', array_map(function($b) {
					return sprintf('%04x', $b);
				}, $bytes));
			}, $arOutput));

			return $sOutput;
		}
	}

?>
