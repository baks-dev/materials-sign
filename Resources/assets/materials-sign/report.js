/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */


executeFunc(function materialsSignPdf()
{
    /* Имя формы */
    ChangeMaterialForm = document.forms.material_sign_report_form;

    if(typeof ChangeMaterialForm === 'undefined')
    {
        return false;
    }

    var object_category = document.getElementById(ChangeMaterialForm.name + '_category');

    if(object_category === null)
    {
        return false;
    }

    object_category.addEventListener('change', function()
    {
        changeObjectCategory(ChangeMaterialForm);

    }, false);


    /** Инициируем календарь */
    document.querySelectorAll('.js-datepicker').forEach((datepicker) =>
    {
        MCDatepicker.create({
            el: '#' + datepicker.id,
            bodyType: 'modal', // ‘modal’, ‘inline’, or ‘permanent’.
            autoClose: false,
            closeOndblclick: true,
            closeOnBlur: false,
            customOkBTN: 'OK',
            customClearBTN: datapickerLang[$locale].customClearBTN,
            customCancelBTN: datapickerLang[$locale].customCancelBTN,
            firstWeekday: datapickerLang[$locale].firstWeekday,
            dateFormat: 'DD.MM.YYYY',
            customWeekDays: datapickerLang[$locale].customWeekDays,
            customMonths: datapickerLang[$locale].customMonths,
        });
    });


    var submit = document.getElementById(ChangeMaterialForm.name + '_material_sign_report');


    submit.addEventListener('click', event =>
        {

            event.preventDefault();

            let formSubmit = true;

            Array.from(ChangeMaterialForm.elements).forEach((input) =>
            {
                let $errorFormHandler = false;


                if(input.validity.valid === false)
                {

                    formSubmit = false;

                    let $placeholderText = false;

                    setTimeout(closeProgress, 1000);

                    /* Поиск полей по LABEL */
                    $label = document.querySelector('label[for="' + input.id + '"]');
                    $placeholderText = $label ? $label.innerHTML : false;

                    if(!$placeholderText)
                    {
                        /* Поиск полей по Placeholder */
                        $placeholderInput = document.querySelector('#' + input.id + '');

                        if($placeholderInput.tagName === 'SELECT')
                        {
                            /* если элемент SELECT - получаем placeholder по первому элементу списка в empty value  */
                            const firstOption = $placeholderInput.options[0];
                            $placeholderText = firstOption.value === '' ? firstOption.textContent : false;
                        } else
                        {
                            $placeholder = $placeholderInput.getAttribute('placeholder');
                            $placeholderText = $placeholder ? $placeholder : false;
                        }
                    }

                    if(!$placeholderText)
                    {
                        $placeholderText = input.id;
                    }

                    if($placeholderText)
                    {
                        $errorFormHandler = '{ "type":"danger" , ' + '"header":"Ошибка заполнения"   , ' + '"message" : "' + $placeholderText + '"}';

                        if($errorFormHandler !== false)
                        {
                            createToast(JSON.parse($errorFormHandler));
                        }
                    }
                }

            });

            if(formSubmit)
            {
                modaHidden();

                setTimeout(() => { ChangeMaterialForm.submit(); }, 300);

            }
        }
    );

    return true;
});

async function changeObjectCategory(forms)
{
    disabledElementsForm(forms);

    document.getElementById('material').classList.add('d-none');
    document.getElementById('offer').classList.add('d-none');
    document.getElementById('variation').classList.add('d-none');
    document.getElementById('modification').classList.add('d-none');

    const data = new FormData(forms);

    let formData = new FormData();
    formData.append(forms.name + '[category]', data.get(forms.name + '[category]'));

    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: formData // body data type must match "Content-Type" header
    })

        //.then((response) => response)
        .then((response) =>
        {

            if(response.status !== 200)
            {
                return false;
            }

            return response.text();

        })

        .then((data) =>
        {
            if(data)
            {

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');

                let preMaterial = result.getElementById('material');


                /** Сбрасываем ошибки валидации */
                preMaterial.querySelectorAll('.is-invalid').forEach((el) => { el.classList.remove('is-invalid'); });
                preMaterial.querySelectorAll('.invalid-feedback').forEach((el) => { el.remove(); });


                preMaterial ?
                    document
                        .getElementById('material')
                        .replaceWith(preMaterial) :
                    preMaterial.innerHTML = '';


                /** SELECT2 */
                let replacer = document.getElementById(forms.name + '_material');
                replacer && replacer.type !== 'hidden' ? preMaterial.classList.remove('d-none') : null;

                /** Событие на изменение модификации */
                if(replacer)
                {
                    if(replacer.tagName === 'SELECT')
                    {
                        new NiceSelect(replacer, {searchable: true});

                        let focus = document.getElementById(forms.name + '_material_select2');
                        focus ? focus.click() : null;
                    }
                }

                ///** сбрасываем зависимые поля */
                let preOffer = document.getElementById('offer');
                preOffer ? preOffer.innerHTML = '' : null;
                preOffer ? preOffer.classList.add('d-none') : null;

                ///** сбрасываем зависимые поля */
                let preVariation = document.getElementById('variation');
                preVariation ? preVariation.innerHTML = '' : null;
                preVariation ? preVariation.classList.add('d-none') : null;

                let preModification = document.getElementById('modification');
                preModification ? preModification.innerHTML = '' : null;
                preModification ? preModification.classList.add('d-none') : null;

                if(replacer)
                {
                    replacer.addEventListener('change', function(event)
                    {
                        changeObjectMaterial(forms);
                        return false;
                    });
                }
            }

            enableElementsForm(forms);
        });
}

async function changeObjectMaterial(forms)
{
    disabledElementsForm(forms);

    document.getElementById('offer').classList.add('d-none');
    document.getElementById('variation').classList.add('d-none');
    document.getElementById('modification').classList.add('d-none');

    //data.delete(forms.name + '[_token]');
    //data.delete(forms.name + '[_offer]');
    //data.delete(forms.name + '[_variation]');
    //data.delete(forms.name + '[_modification]');

    const data = new FormData(forms);
    const formData = new FormData();
    formData.append(forms.name + '[material]', data.get(forms.name + '[material]'));

    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: formData // body data type must match "Content-Type" header
    })

        //.then((response) => response)
        .then((response) =>
        {

            if(response.status !== 200)
            {
                return false;
            }

            return response.text();

        })

        .then((data) =>
        {

            if(data)
            {

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');


                let preOffer = result.getElementById('offer');

                preOffer ? document.getElementById('offer').replaceWith(preOffer) : preOffer.innerHTML = '';

                if(preOffer)
                {

                    /** SELECT2 */

                    let replaceOfferId = forms.name + '_offer';

                    let replacer = document.getElementById(replaceOfferId);
                    replacer && replacer.type !== 'hidden' ? preOffer.classList.remove('d-none') : null;


                    if(replacer.tagName === 'SELECT')
                    {
                        new NiceSelect(replacer, {searchable: true});

                        let focus = document.getElementById(forms.name + '_offer_select2');
                        focus ? focus.click() : null;
                    }

                }


                /** сбрасываем зависимые поля */
                let preVariation = document.getElementById('variation');
                preVariation ? preVariation.innerHTML = '' : null;
                preVariation ? preVariation.classList.add('d-none') : null;

                let preModification = document.getElementById('modification');
                preModification ? preModification.innerHTML = '' : null;
                preModification ? preModification.classList.add('d-none') : null;


                /** Событие на изменение торгового предложения */
                let offerChange = document.getElementById(forms.name + '_offer');

                if(offerChange)
                {

                    offerChange.addEventListener('change', function(event)
                    {
                        changeObjectOffer(forms);
                        return false;
                    });
                }


                // return;
                //
                //
                // /** Изменияем список целевых складов */
                // let warehouse = result.getElementById('targetWarehouse');
                //
                //
                // document.getElementById('targetWarehouse').replaceWith(warehouse);
                // document.getElementById('new_order_form_targetWarehouse').addEventListener('change', changeObjectWarehause, false);
                //
                // new NiceSelect(document.getElementById('new_order_form_targetWarehouse'), {
                //     searchable: true,
                //     id: 'select2-' + replaceId
                // });

            }

            enableElementsForm(forms);
        });
}

async function changeObjectOffer(forms)
{
    disabledElementsForm(forms);

    document.getElementById('variation').classList.add('d-none');
    document.getElementById('modification').classList.add('d-none');

    //const data = new FormData(forms);
    //data.delete(forms.name + '[_token]');
    //data.delete(forms.name + '[_variation]');
    //data.delete(forms.name + '[_modification]');


    const data = new FormData(forms);
    const formData = new FormData();
    formData.append(forms.name + '[offer]', data.get(forms.name + '[offer]'));

    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: formData // body data type must match "Content-Type" header
    })

        //.then((response) => response)
        .then((response) =>
        {

            if(response.status !== 200)
            {
                return false;
            }

            return response.text();

        })

        .then((data) =>
        {

            if(data)
            {

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');


                let preVariation = result.getElementById('variation');

                if(preVariation)
                {

                    document.getElementById('variation').replaceWith(preVariation);

                    /** SELECT2 */

                    let replacer = document.getElementById(forms.name + '_variation');
                    replacer && replacer.type !== 'hidden' ? preVariation.classList.remove('d-none') : null;

                    if(replacer)
                    {

                        if(replacer.tagName === 'SELECT')
                        {
                            new NiceSelect(replacer, {searchable: true});

                            let focus = document.getElementById(forms.name + '_variation_select2');
                            focus ? focus.click() : null;

                            replacer.addEventListener('change', function(event)
                            {
                                changeObjectVariation(forms);
                                return false;
                            });

                        }
                    }

                }

                let preModification = document.getElementById('modification');
                preModification ? preModification.innerHTML = '' : null;
                preModification ? preModification.classList.add('d-none') : null;


            }

            enableElementsForm(forms);
        });
}

async function changeObjectVariation(forms)
{

    disabledElementsForm(forms);

    document.getElementById('modification').classList.add('d-none');

    //const data = new FormData(forms);
    //data.delete(forms.name + '[_token]');
    //data.delete(forms.name + '[_modification]');

    const data = new FormData(forms);
    const formData = new FormData();
    formData.append(forms.name + '[variation]', data.get(forms.name + '[variation]'));


    await fetch(forms.action, {
        method: forms.method, // *GET, POST, PUT, DELETE, etc.
        //mode: 'same-origin', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },

        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        body: formData // body data type must match "Content-Type" header
    })

        //.then((response) => response)
        .then((response) =>
        {

            if(response.status !== 200)
            {
                return false;
            }

            return response.text();

        })

        .then((data) =>
        {

            if(data)
            {

                var parser = new DOMParser();
                var result = parser.parseFromString(data, 'text/html');

                let preModification = result.getElementById('modification');


                if(preModification)
                {

                    document.getElementById('modification').replaceWith(preModification);

                    /** SELECT2 */
                    let replacer = document.getElementById(forms.name + '_modification');
                    replacer && replacer.type !== 'hidden' ? preModification.classList.remove('d-none') : null;

                    console.log(replacer && replacer.type !== 'hidden');

                    /** Событие на изменение модификации */
                    if(replacer)
                    {
                        if(replacer.tagName === 'SELECT')
                        {
                            new NiceSelect(replacer, {searchable: true});

                            let focus = document.getElementById(forms.name + '_modification_select2');
                            focus ? focus.click() : null;

                            //replacer.addEventListener('change', function(event)
                            //{
                            //    selectTotal(this)
                            //    return false;
                            //});

                        }
                    }
                }
            }

            enableElementsForm(forms);
        });
}


