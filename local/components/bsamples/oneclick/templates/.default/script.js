BX.ready(
    function () {
        mountTelRegisterForm();

        /**
         * Монтирует компонент из template
         * @param id - селектор template
         * @param callback - вызывается при монтировании, node компонента привязывается к this, нужен для добавления событий
         */
        function mountComponent(id, callback) {
            const template = document.querySelector(id);
            const node = template.content.cloneNode(true);
            const root = document.querySelector('#quickorder-phone_root');
            requestAnimationFrame(() => {
                callback.apply(node);
                root.innerHTML = '';
                root.append(node);
            });
        }

        /**
         * Запросить код
         * @param tel - номер телефона
         * @returns {Promise<{isSuccess, userID, isNeedRegistration}>} - статус запроса, userID и зарегестрирован ли юзер
         */
        async function submitTel(tel) {
            const regexp = /\d/g;
            const user_phone = tel.match(regexp).join('');
            if (user_phone.length === 11) {
                let json = await BX.ajax.runComponentAction('customcomponent:sms', 'ajaxRequest', {
                    mode: 'class',
                    data: {
                        'param': {
                            phone: Number(user_phone),
                        }
                    }
                });
                const {data} = json;
                const {isSuccess, userID} = data;
                return {
                    isSuccess,
                    userID,
                };
            } else {
                return {
                    isSuccess: false,
                    userID: '',
                };
            }
        }

        /**
         * Отправить код
         * @param userID - userID
         * @param otp - код
         * @returns {Promise<{isSuccess}>} - статус запроса
         */
        async function submitCode(userID, otp) {
            const param = {
                otp: Number(otp),
                userID: Number(userID),
            }
            let json = await BX.ajax.runComponentAction('customcomponent:sms', 'ajaxRequest', {
                mode: 'class',
                data: {
                    'param': param
                }
            });
            const {data} = json;
            const {isSuccess} = data;
            return {
                isSuccess,
            };
        }

        function mountSuccess() {
            mountComponent('#quickorder_success', function () {

            })
        }

        async function addOrder(comment, productId) {
            let {data} = await BX.ajax.runComponentAction('bsamples:oneclick', 'ajaxRequest', {
                mode: 'class',
                data: {
                    param: {
                        addOrder: true,
                        comment: comment,
                        prodId: Number(productId)
                    }
                }
            });

            const {isSuccess, order: {ID}} = data;
            return {
                isSuccess,
                ID,
                orderData: data.order
            }
        }

        /**
         * Отобразить форму ввода телефона
         */
        function mountTelRegisterForm() {
            mountComponent('#quickorder_tel_template', function () {
                const error = this.querySelector('.error');
                const comment = this.querySelector('.quickorder_comment');
                const input = this.querySelector('.auth_tel_input');
                const isAuth = input.classList.contains('quickorder_is-auth');
                const form = this.firstElementChild;
                const inputProductId = this.querySelector('.quickorder_product-id');
                const login = this.querySelector('.btn-auth_getcode');
                if (input.value) {
                    login.classList.remove('btn-auth_login_disable');
                }
                input.oninput = () => {
                    const regexp = /\d/g;
                    const phone = input.value.match(regexp).join('');
                    input.classList.remove('error');
                    error.innerHTML = '';
                    if (phone.length === 11) {
                        login.classList.remove('btn-auth_login_disable');
                    } else {
                        login.classList.add('btn-auth_login_disable');
                    }
                };
                form.onsubmit = async (e) => {
                    e.preventDefault();
                    form.classList.add('quickorder_preloader');
                    if (isAuth) {
                        const productId = inputProductId ? inputProductId.value : 1;
                        let {isSuccess, ID, orderData} = await addOrder(comment.value, productId);
                        if (isSuccess) {
                            mountSuccess();
                        } else {
                            error.innerHTML = 'Ошибка создания заказа!';
                        }
                    } else {
                        const tel = input.value;
                        const {isSuccess, userID} = await submitTel(tel);
                        if (isSuccess) {
                            mountSendCodeForm(tel, userID, comment.value);
                        } else {
                            error.innerHTML = 'Некорректный номер телефона!';
                        }
                    }
                    form.classList.remove('quickorder_preloader');
                }
                const maskedPhone = $('#quickorder-phone_root .auth_tel_input.labeled-phone');
                maskedPhone && maskedPhone.mask('+7 (000) 000-00-00', {
                    clearIfNotMatch: false,
                    onKeyPress: function (cep, e, field, options) {
                        let masks = ['+7 (000) 000-00-00', '8 (000) 000-00-00'];
                        if (cep === '8 (') {
                            $('.labeled-phone').mask(masks[0], options);
                            field.val('+');
                        }
                    }
                });
            })
        }

        /**
         * Отобразить форму отправки кода
         * @param tel - телефон
         * @param userID - userID
         * @param comment
         */
        function mountSendCodeForm(tel, userID, comment) {
            mountComponent('#quickorder_code_template', function () {
                let id = userID;
                const error = this.querySelector('.error');
                const currentTel = this.querySelector('.auth__title__lead');
                const input = this.querySelector('.auth_code');
                const time = this.querySelector('.auth_time');
                const counter = this.querySelector('.auth_time_counter');
                const login = this.querySelector('.btn-auth_login');
                const newcode = this.querySelector('.btn-auth_newcode');
                const changetel = this.querySelector('.btn-auth_changetel');
                const inputProductId = this.querySelector('.quickorder_product-id');
                const form = this.firstElementChild;
                currentTel.innerHTML = tel;
                let count;
                let timerId;
                const startTimer = () => {
                    clearInterval(timerId);
                    time.classList.remove('auth_time_is-up');
                    count = 60;
                    counter.innerHTML = '00:60';
                    timerId = setInterval(() => {
                        count = count - 1;
                        if (count < 1) {
                            clearInterval(timerId);
                            time.classList.add('auth_time_is-up');
                            return;
                        }
                        counter.innerHTML = `00:${(count < 10) ? '0' + count : count}`;
                    }, 1000);
                }
                startTimer()
                input.oninput = (e) => {
                    input.classList.remove('error');
                    error.innerHTML = '';
                    if (String(e.target.value).length === 4) {
                        login.classList.remove('btn-auth_login_disable');
                    } else {
                        login.classList.add('btn-auth_login_disable');
                    }
                };
                newcode.onclick = async () => {
                    form.classList.add('quickorder_preloader');
                    const {isSuccess, userID} = await submitTel(tel);
                    if (isSuccess) {
                        id = userID;
                        startTimer();
                    } else {
                        console.error('error');
                    }
                    form.classList.remove('quickorder_preloader');
                }
                changetel.onclick = () => {
                    clearInterval(timerId);
                    mountTelRegisterForm();
                }
                form.onsubmit = async (e) => {
                    e.preventDefault();
                    form.classList.add('quickorder_preloader');
                    const code = input.value;

                    const {isSuccess} = await submitCode(id, code);

                    if (isSuccess) {
                        const productId = inputProductId ? inputProductId.value : 1;
                        let {isSuccess, ID, orderData} = await addOrder(comment, productId);
                        if (isSuccess) {
                            mountSuccess();
                        } else {
                            //handle error
                        }
                    } else {
                        error.innerHTML = 'Неверный код!';
                        input.classList.add('error');
                    }
                    form.classList.remove('quickorder_preloader');
                }
            })
        }
    }
)
