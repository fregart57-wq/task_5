<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

AddEventHandler('main', 'OnPageStart', 'RegisterLogEventHandlers');

function RegisterLogEventHandlers()
{
    if (!CModule::IncludeModule('iblock')) {
        return;
    }

    AddEventHandler('iblock', 'OnAfterIBlockElementAdd',    'LogIBlockElementChange');
    AddEventHandler('iblock', 'OnAfterIBlockElementUpdate', 'LogIBlockElementChange');
}

function LogIBlockElementChange(&$arFields)
{
    $LOG_IBLOCK_CODE = 'LOG';

    $iblockId = (int)($arFields['IBLOCK_ID'] ?? 0);
    if ($iblockId <= 0) {
        return;
    }

    $iblockRes = CIBlock::GetByID($iblockId);
    if (!($iblock = $iblockRes->Fetch())) {
        return;
    }

    if ($iblock['CODE'] === $LOG_IBLOCK_CODE) {
        return;
    }

    $elementId   = (int)$arFields['ID'];
    $elementName = trim($arFields['NAME'] ?? '');

    $activeFrom = $arFields['TIMESTAMP_X'] ?? $arFields['DATE_CREATE'] ?? date('d.m.Y H:i:s');

    $logIblockRes = CIBlock::GetList([], ['CODE' => $LOG_IBLOCK_CODE, 'ACTIVE' => 'Y']);
    if (!($logIblock = $logIblockRes->Fetch())) {
        return;
    }
    $logIblockId = (int)$logIblock['ID'];

    $sectionCode = $iblock['CODE'];
    $sectionName = $iblock['NAME'];

    $sectRes = CIBlockSection::GetList(
        [],
        [
            'IBLOCK_ID' => $logIblockId,
            'CODE'      => $sectionCode
        ],
        false,
        ['ID']
    );

    if ($arSect = $sectRes->Fetch()) {
        $logSectionId = (int)$arSect['ID'];
    } else {
        $bs = new CIBlockSection;
        $arFieldsSect = [
            'ACTIVE'      => 'Y',
            'IBLOCK_ID'   => $logIblockId,
            'NAME'        => $sectionName,
            'CODE'        => $sectionCode,
            'SORT'        => 500,
        ];
        $logSectionId = $bs->Add($arFieldsSect);

        if (!$logSectionId) {
            return;
        }
    }

    $path = [$iblock['NAME']];

    if (!empty($arFields['IBLOCK_SECTION_ID'])) {
        $nav = CIBlockSection::GetNavChain(false, $arFields['IBLOCK_SECTION_ID'], ['NAME', 'DEPTH_LEVEL']);
        while ($arNav = $nav->GetNext()) {
            $path[] = $arNav['NAME'];
        }
    }
    $path[] = $elementName;

    $previewText = implode(' -> ', $path);

    $filter = [
        'IBLOCK_ID' => $logIblockId,
        'NAME'      => (string)$elementId,
        'SECTION_ID'=> $logSectionId,
    ];

    $rsLog = CIBlockElement::GetList([], $filter, false, false, ['ID']);
    $el = new CIBlockElement;

    $arLoad = [
        'IBLOCK_ID'         => $logIblockId,
        'IBLOCK_SECTION_ID' => $logSectionId,
        'NAME'              => (string)$elementId,
        'ACTIVE'            => 'Y',
        'ACTIVE_FROM'       => $activeFrom,
        'PREVIEW_TEXT'      => $previewText,
        'PREVIEW_TEXT_TYPE' => 'text',
    ];

    if ($arLogEl = $rsLog->Fetch()) {
        $el->Update($arLogEl['ID'], $arLoad);
    } else {
        $el->Add($arLoad);
    }
}

function ClearOldLogsAgent()
{
    $LOG_IBLOCK_CODE = 'LOG';

    $logIblock = CIBlock::GetList([], ['CODE' => $LOG_IBLOCK_CODE])->Fetch();
    if (!$logIblock) {
        return "ClearOldLogsAgent();";
    }

    $logIblockId = (int)$logIblock['ID'];

    $rsElements = CIBlockElement::GetList(
        ['ID' => 'DESC'],
        ['IBLOCK_ID' => $logIblockId],
        false,
        false,
        ['ID']
    );

    $idsToDelete = [];
    $count = 0;

    while ($arEl = $rsElements->Fetch()) {
        $count++;
        if ($count > 10) {
            $idsToDelete[] = $arEl['ID'];
        }
    }

    if (!empty($idsToDelete)) {
        foreach ($idsToDelete as $id) {
            CIBlockElement::Delete($id);
        }
    }

    return "ClearOldLogsAgent();";
}