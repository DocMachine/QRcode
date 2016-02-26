<?php

/*
 * Based on libqrencode C library distributed under LGPL 2.1
 * Copyright (C) 2006, 2007, 2008, 2009 Kentaro Fukuchi <fukuchi@megaui.net>
 *
 * PHP QR Code is distributed under LGPL 3
 * Copyright (C) 2010 Dominik Dzienia <deltalab at poczta dot fm>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

/** Raw Code holder.
 * Contains encoded code data before there are spatialy distributed into frame and masked.
 * Here goes dividing data into blocks and calculating ECC stream.
 */
class QRrawcode {

    public $version;                ///< __Integer__ code Version
    public $datacode = array();     ///< __Array__ data stream
    public $ecccode = array();      ///< __Array__ ECC Stream
    public $blocks;                 ///< __Integer__ RS Blocks count
    public $rsblocks = array();     ///< __Array__ of RSblock, ECC code blocks
    public $count;                  ///< __Integer__ position of currently processed ECC code
    public $dataLength;             ///< __Integer__ data stream length
    public $eccLength;              ///< __Integer__ ECC stream length
    public $b1;                     ///< __Integer__ width of code in pixels, used as a modulo base for column overflow
    
    //----------------------------------------------------------------------
    /** Raw Code holder Constructor 
    @param QRinput $input input stream
    */
    public function __construct(QRinput $input)
    {
        $spec = array(0,0,0,0,0);
        
        $this->datacode = $input->getByteStream();
        if(is_null($this->datacode)) {
            throw new Exception('null imput string');
        }

        QRspec::getEccSpec($input->getVersion(), $input->getErrorCorrectionLevel(), $spec);

        $this->version = $input->getVersion();
        $this->b1 = QRspec::rsBlockNum1($spec);
        $this->dataLength = QRspec::rsDataLength($spec);
        $this->eccLength = QRspec::rsEccLength($spec);
        $this->ecccode = array_fill(0, $this->eccLength, 0);
        $this->blocks = QRspec::rsBlockNum($spec);
        
        $ret = $this->init($spec);
        if($ret < 0) {
            throw new Exception('block alloc error');
            return null;
        }

        $this->count = 0;
    }
    
    //----------------------------------------------------------------------
    /** Initializes Raw Code according to current code speciffication
    @param Array $spec code speciffigation, as provided by QRspec
    */
    public function init(array $spec)
    {
        $dl = QRspec::rsDataCodes1($spec);
        $el = QRspec::rsEccCodes1($spec);
        $rs = QRrs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);
        

        $blockNo = 0;
        $dataPos = 0;
        $eccPos = 0;
        for($i=0; $i<QRspec::rsBlockNum1($spec); $i++) {
            $ecc = array_slice($this->ecccode,$eccPos);
            $this->rsblocks[$blockNo] = new QRrsblock($dl, array_slice($this->datacode, $dataPos), $el,  $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode,0, $eccPos), $ecc);
            
            $dataPos += $dl;
            $eccPos += $el;
            $blockNo++;
        }

        if(QRspec::rsBlockNum2($spec) == 0)
            return 0;

        $dl = QRspec::rsDataCodes2($spec);
        $el = QRspec::rsEccCodes2($spec);
        $rs = QRrs::init_rs(8, 0x11d, 0, 1, $el, 255 - $dl - $el);
        
        if($rs == NULL) return -1;
        
        for($i=0; $i<QRspec::rsBlockNum2($spec); $i++) {
            $ecc = array_slice($this->ecccode,$eccPos);
            $this->rsblocks[$blockNo] = new QRrsblock($dl, array_slice($this->datacode, $dataPos), $el, $ecc, $rs);
            $this->ecccode = array_merge(array_slice($this->ecccode,0, $eccPos), $ecc);
            
            $dataPos += $dl;
            $eccPos += $el;
            $blockNo++;
        }

        return 0;
    }
    
    //----------------------------------------------------------------------
    /** Gets ECC code 
    @return Integer ECC byte for current object position
    */
    public function getCode()
    {
        $ret;

        if($this->count < $this->dataLength) {
            $row = $this->count % $this->blocks;
            $col = $this->count / $this->blocks;
            if($col >= $this->rsblocks[0]->dataLength) {
                $row += $this->b1;
            }
            $ret = $this->rsblocks[$row]->data[$col];
        } else if($this->count < $this->dataLength + $this->eccLength) {
            $row = ($this->count - $this->dataLength) % $this->blocks;
            $col = ($this->count - $this->dataLength) / $this->blocks;
            $ret = $this->rsblocks[$row]->ecc[$col];
        } else {
            return 0;
        }
        $this->count++;
        
        return $ret;
    }
}