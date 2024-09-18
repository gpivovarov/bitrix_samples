<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
/** @var array $arParams */
/** @var array $arResult */

$bIsAuth = !!$arResult['userData']['id'];
?>
<div>
    <section id="quickorder-phone_root"></section>
</div>
<template id="quickorder_code_template">
    <form class="auth-phone--form" onclick="event.stopPropagation()">
        <? if ($arParams['PRODUCT_ID'] > 0) : ?>
            <input type="hidden" class="quickorder_product-id" name="productId" value="<?=(int)$arParams['PRODUCT_ID']?>">
        <? endif; ?>
        <div class="auth-phone--form---title">
            <div class="auth__title">Введите код</div>
            <p>
                Введите код из SMS, отправленного на номер
                <span class="auth__title__lead"></span><br>
                <button type="button" class="auth__title__button btn-auth_changetel">Изменить номер</button>
            </p>
            <div class="auth_time">
                <div class="auth_time_left">Получить новый код можно через <span class="auth_time_counter"></span></div>
                <div class="auth_time_newcode">
                    <div>Время действия кода истекло</div>
                    <button type="button" class="auth__title__button btn-auth_newcode">Получить новый код</button>
                </div>
            </div>
        </div>
        <div class="auth-phone--form---body">
            <label class="auth_tel" for="auth_tel">
                <span class="auth_tel_label">Код из SMS</span>
                <input class="auth_tel_input auth_code" name="auth_code" type="text" maxlength="4" placeholder="____">
                <span class="error"></span>
            </label>
        </div>
        <div class="auth-phone--form---footer">
            <button class="btn btn-active btn-auth btn-auth_login btn-auth_login_disable">
                Оформить заказ
            </button>
        </div>
    </form>
</template>
<template id="quickorder_tel_template">
    <form class="auth-phone--form" onclick="event.stopPropagation()">
        <? if ($arParams['PRODUCT_ID'] > 0) : ?>
            <input type="hidden" class="quickorder_product-id" name="productId" value="<?=(int)$arParams['PRODUCT_ID'] ?>">
        <? endif; ?>
        <div class="auth-phone--form---title">
            <div class="auth__title">Быстрый заказ</div>
            <p>Заполните свои контактные данные, и наш менеджер свяжется с вами для уточнения деталей заказа</p>
        </div>
        <div class="auth-phone--form---body">
            <label class="auth_tel">
                <span class="auth_tel_label">Телефон<span class="quickorder_required">*</span></span>
                <input
                        class="auth_tel_input labeled-phone <?=$bIsAuth ? 'quickorder_is-auth' : '';?>"
                        name="auth_tel"
                        type="tel"
                        placeholder="+7(___) ___-__-__"
                        value=""
                        <?=$bIsAuth ? 'disabled' : '';?>
                >
                <span class="error"></span>
            </label>
            <label class="auth_tel">
                <span class="auth_tel_label">Комментарий</span>
                <textarea class="auth_tel_input quickorder_comment"
                          placeholder="Текст комментария"
                ></textarea>
                <span class="error"></span>
            </label>
        </div>
        <div class="auth-phone--form---footer">
            <button class="btn btn-active btn-auth btn-auth_getcode btn-auth_login_disable">
                <?= $bIsAuth ? 'Оформить заказ' : 'Получить код';?>
            </button>
        </div>
        <div class="auth_agree">
            Отправляя данную форму, вы автоматически принимаете,
            <a class="auth_agree_privacy"
               href="/page/"
               target="_blank"
            >соглашение о пользовательских данных.</a>
        </div>
    </form>
</template>
<template id="quickorder_success" onclick="event.stopPropagation()">
    <div class="auth-phone--form">
        <div class="auth-phone--form---title">
            <div class="auth__title">Спасибо за заказ!</div>
            <p>Наш менеджер скоро свяжется с вами для уточнения деталей.</p>
        </div>
        <div class="auth-phone--form---footer">
            <a href="/thx/" class="btn btn-active btn-auth">
                ок
            </a>
        </div>
        <div id="promocode-element-container"></div>
    </div>
</template>

